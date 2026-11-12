<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Regras do PO de 12/11/2026 para os postos (SDV-OPE-018 §14):
 *
 * - um posto só pode estar com um operador por vez: índice único parcial em
 *   `operador_turnos (posto_id)` enquanto o turno está aberto. Bancos que já
 *   tenham dois turnos abertos no mesmo posto ficam só com o mais recente; os
 *   demais são encerrados com o motivo `posto_compartilhado`;
 * - cada posto tem o seu caixa: `caixa_turnos.posto_id` e um caixa aberto por
 *   posto. Caixas antigos ficam sem posto e continuam com o seu operador;
 * - permissão `postos.liberar` para Gestor e Administrador liberarem um posto
 *   preso (computador travado, operador que saiu sem fechar).
 */
return new class extends Migration
{
    private const PERMISSAO = 'postos.liberar';

    public function up(): void
    {
        $abertos = DB::table('operador_turnos')->whereNull('encerrado_em')->orderByDesc('iniciado_em')->get(['id', 'posto_id']);
        $repetidos = $abertos->groupBy('posto_id')->flatMap(fn ($turnos) => $turnos->slice(1)->pluck('id'));
        if ($repetidos->isNotEmpty()) {
            DB::table('operador_turnos')->whereIn('id', $repetidos->all())->update([
                'encerrado_em' => now(),
                'motivo_encerramento' => 'posto_compartilhado',
                'updated_at' => now(),
            ]);
        }
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS operador_turnos_posto_ativo_unique ON operador_turnos (posto_id) WHERE encerrado_em IS NULL');

        // Idempotente: uma execução interrompida (banco travado) pode ser repetida.
        if (! Schema::hasColumn('caixa_turnos', 'posto_id')) {
            Schema::table('caixa_turnos', function (Blueprint $table) {
                $table->foreignUuid('posto_id')->nullable()->after('terminal')->constrained('postos')->restrictOnDelete();
            });
        }
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS caixa_turnos_posto_aberto_unique ON caixa_turnos (posto_id) WHERE status = 'aberto' AND posto_id IS NOT NULL");

        $this->garantirPermissao();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS caixa_turnos_posto_aberto_unique');
        Schema::table('caixa_turnos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('posto_id');
        });
        DB::statement('DROP INDEX IF EXISTS operador_turnos_posto_ativo_unique');
        // A permissão fica: pode já estar em uso em perfis ajustados pelo administrador.
    }

    /** Catálogo e perfis Gestor/Administrador já existentes (bancos semeados antes desta regra). */
    private function garantirPermissao(): void
    {
        $permissaoId = DB::table('permissoes')->where('chave', self::PERMISSAO)->value('id');
        if ($permissaoId === null) {
            $permissaoId = (string) Str::uuid7();
            DB::table('permissoes')->insert([
                'id' => $permissaoId,
                'chave' => self::PERMISSAO,
                'modulo' => 'acessos',
                'descricao' => 'Liberar um posto preso por outro operador, com motivo; o caixa vai para conferência',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $perfis = DB::table('perfis')->whereIn('nome', ['Gestor', 'Administrador'])->get(['id', 'implantacao_id']);
        foreach ($perfis as $perfil) {
            $existe = DB::table('perfil_permissoes')->where('perfil_id', $perfil->id)->where('permissao_id', $permissaoId)->exists();
            if (! $existe) {
                DB::table('perfil_permissoes')->insert([
                    'id' => (string) Str::uuid7(),
                    'implantacao_id' => $perfil->implantacao_id,
                    'perfil_id' => $perfil->id,
                    'permissao_id' => $permissaoId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};

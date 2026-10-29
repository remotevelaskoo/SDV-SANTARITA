<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fechamento de caixa por turno (AJ-008, SDV-UXR-014 §§5.4–5.6).
 *
 * - cada caixa pertence a um operador e, quando houver, ao posto assumido;
 * - no máximo um caixa aberto por operador e por posto na implantação,
 *   garantido no banco por índices únicos parciais;
 * - o fechamento guarda o retrato dos totais conciliados (contribuições
 *   pagas, outras entradas, saídas, estornos e quantidades), que não muda
 *   depois do encerramento.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicados = DB::table('caixa_turnos')
            ->where('status', 'aberto')
            ->whereNotNull('operator_id')
            ->select('implantacao_id', 'operator_id')
            ->groupBy('implantacao_id', 'operator_id')
            ->havingRaw('count(*) > 1')
            ->count();
        if ($duplicados > 0) {
            throw new RuntimeException("Há {$duplicados} operador(es) com mais de um caixa aberto. Feche os caixas excedentes antes de migrar.");
        }

        Schema::table('caixa_turnos', function (Blueprint $table) {
            $table->foreignUuid('posto_id')->nullable()->after('terminal')->constrained('postos')->restrictOnDelete();
            $table->string('numero_fechamento', 24)->nullable()->after('status');
            $table->decimal('contribuicoes_total', 10, 2)->nullable()->after('opening_balance');
            $table->decimal('outras_entradas_total', 10, 2)->nullable()->after('contribuicoes_total');
            $table->decimal('saidas_total', 10, 2)->nullable()->after('outras_entradas_total');
            $table->decimal('estornos_total', 10, 2)->nullable()->after('saidas_total');
            $table->unsignedInteger('pagantes_quantidade')->nullable();
            $table->unsignedInteger('isentos_quantidade')->nullable();
            $table->unsignedInteger('nao_contribuintes_quantidade')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['implantacao_id', 'numero_fechamento']);
        });

        DB::statement("CREATE UNIQUE INDEX caixa_turnos_operador_aberto_unique ON caixa_turnos (implantacao_id, operator_id) WHERE status = 'aberto'");
        DB::statement("CREATE UNIQUE INDEX caixa_turnos_posto_aberto_unique ON caixa_turnos (implantacao_id, posto_id) WHERE status = 'aberto' AND posto_id IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS caixa_turnos_posto_aberto_unique');
        DB::statement('DROP INDEX IF EXISTS caixa_turnos_operador_aberto_unique');

        Schema::table('caixa_turnos', function (Blueprint $table) {
            $table->dropUnique(['implantacao_id', 'numero_fechamento']);
            $table->dropConstrainedForeignId('closed_by');
            $table->dropConstrainedForeignId('posto_id');
            $table->dropColumn([
                'numero_fechamento', 'contribuicoes_total', 'outras_entradas_total', 'saidas_total', 'estornos_total',
                'pagantes_quantidade', 'isentos_quantidade', 'nao_contribuintes_quantidade',
            ]);
        });
    }
};

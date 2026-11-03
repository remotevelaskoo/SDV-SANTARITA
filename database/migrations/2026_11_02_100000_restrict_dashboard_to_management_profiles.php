<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permission = DB::table('permissoes')->where('chave', 'dashboard.visualizar')->value('id');

        if ($permission === null) {
            return;
        }

        // Corrige instalações existentes sem executar seeders de demonstração.
        // Perfis personalizados não são reescritos; sua matriz continua administrável.
        $restricted = DB::table('perfis')->whereIn('nome', ['Porteiro/Caixa', 'Auditor', 'Administrador'])->pluck('id');
        DB::table('perfil_permissoes')->where('permissao_id', $permission)->whereIn('perfil_id', $restricted)->delete();

        foreach (DB::table('implantacoes')->pluck('id') as $implantacaoId) {
            $direction = DB::table('perfis')->where('implantacao_id', $implantacaoId)->where('nome', 'Direção')->value('id');

            if ($direction === null) {
                $direction = (string) Str::uuid7();
                DB::table('perfis')->insert([
                    'id' => $direction, 'implantacao_id' => $implantacaoId,
                    'nome' => 'Direção', 'status' => 'ativo', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $profiles = DB::table('perfis')->where('implantacao_id', $implantacaoId)->whereIn('nome', ['Gestor', 'Direção'])->pluck('id');
            foreach ($profiles as $profileId) {
                if (! DB::table('perfil_permissoes')->where('perfil_id', $profileId)->where('permissao_id', $permission)->exists()) {
                    DB::table('perfil_permissoes')->insert([
                        'id' => (string) Str::uuid7(), 'implantacao_id' => $implantacaoId,
                        'perfil_id' => $profileId, 'permissao_id' => $permission,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Não reabrir permissões gerenciais automaticamente num rollback.
        // Ajustes de concessão continuam disponíveis em Perfis e permissões.
    }
};

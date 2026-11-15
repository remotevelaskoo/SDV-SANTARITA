<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Decisão do PO (15/11/2026): o Administrador também vê o Dashboard.
 * Revê a restrição de 02/11 (AJ-009) só para o perfil Administrador;
 * Porteiro/Caixa e Auditor continuam sem o painel gerencial.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = DB::table('permissoes')->where('chave', 'dashboard.visualizar')->value('id');

        if ($permission === null) {
            return;
        }

        foreach (DB::table('perfis')->where('nome', 'Administrador')->get(['id', 'implantacao_id']) as $perfil) {
            $jaTem = DB::table('perfil_permissoes')->where('perfil_id', $perfil->id)->where('permissao_id', $permission)->exists();
            if (! $jaTem) {
                DB::table('perfil_permissoes')->insert([
                    'id' => (string) Str::uuid7(), 'implantacao_id' => $perfil->implantacao_id,
                    'perfil_id' => $perfil->id, 'permissao_id' => $permission,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Não retirar permissões num rollback; ajuste em Perfis e permissões.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Separates read-only access from write capabilities without re-granting
 * privileges to existing auditors or custom profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'imoveis.gerenciar' => ['imoveis', 'Criar, editar e alterar situação de imóveis'],
            'veiculos.gerenciar' => ['veiculos', 'Criar, editar e alterar situação de veículos'],
            'empresas.gerenciar' => ['empresas', 'Criar, editar e alterar situação de empresas'],
        ];

        foreach ($permissions as $key => [$module, $description]) {
            if (! DB::table('permissoes')->where('chave', $key)->exists()) {
                DB::table('permissoes')->insert([
                    'id' => (string) Str::uuid7(),
                    'chave' => $key,
                    'modulo' => $module,
                    'descricao' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $permissionId = DB::table('permissoes')->where('chave', $key)->value('id');

            foreach (DB::table('perfis')->whereIn('nome', ['Gestor', 'Administrador'])->get(['id', 'implantacao_id']) as $perfil) {
                $alreadyGranted = DB::table('perfil_permissoes')
                    ->where('perfil_id', $perfil->id)
                    ->where('permissao_id', $permissionId)
                    ->exists();

                if (! $alreadyGranted) {
                    DB::table('perfil_permissoes')->insert([
                        'id' => (string) Str::uuid7(),
                        'implantacao_id' => $perfil->implantacao_id,
                        'perfil_id' => $perfil->id,
                        'permissao_id' => $permissionId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Do not restore write access to read-only profiles on rollback.
        // Administrators can manage these privileges in Profiles and permissions.
    }
};

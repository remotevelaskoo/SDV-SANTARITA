<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Garante que a implantação única de hoje (Santa Rita) exista assim que a
 * fundação multi-implantação é criada, para que as migrations seguintes
 * (que atribuem implantacao_id a tabelas já existentes) tenham algo válido
 * para referenciar. `PortariaDemoSeeder`/`FoundationSeeder` continuam
 * responsáveis pelo nome definitivo — este passo só bootstrapa o registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('implantacoes')->exists()) {
            return;
        }

        $organizacaoId = (string) Str::uuid7();

        DB::table('organizacoes')->insert([
            'id' => $organizacaoId,
            'nome' => 'Soluções do Vale Tecnologia',
            'status' => 'ativa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('implantacoes')->insert([
            'id' => (string) Str::uuid7(),
            'organizacao_id' => $organizacaoId,
            'nome' => 'Santa Rita',
            'slug' => 'santa-rita',
            'status' => 'ativa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Bootstrap idempotente; a reversão é responsabilidade das migrations
        // que criam organizacoes/implantacoes.
    }
};

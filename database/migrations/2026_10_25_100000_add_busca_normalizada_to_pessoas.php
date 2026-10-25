<?php

use App\Support\NormalizadorBusca;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Busca da Validação de Entrada (AJ-006, fatia 1, docs/020 §2).
 *
 * - `pessoas.nome_busca`: nome e nome social sem acento e em minúsculas;
 * - `pessoa_contatos.valor_busca`: telefone só com dígitos (e-mail em minúsculas);
 * - índice por documento normalizado sem o tipo, porque o operador digita
 *   o número sem dizer se é CPF, RG ou CNH.
 *
 * Os valores existentes são preenchidos aqui, sem alterar nenhuma outra coluna.
 * No PostgreSQL, quando a extensão pg_trgm já estiver instalada, o nome
 * ganha também um índice trigram para a busca por trecho (`LIKE '%termo%'`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $table->string('nome_busca')->nullable()->after('nome_social');
            $table->index(['implantacao_id', 'nome_busca']);
        });

        Schema::table('pessoa_contatos', function (Blueprint $table) {
            $table->string('valor_busca', 120)->nullable()->after('valor');
            $table->index(['implantacao_id', 'valor_busca']);
        });

        Schema::table('pessoa_documentos', function (Blueprint $table) {
            $table->index(['implantacao_id', 'valor_normalizado']);
        });

        DB::table('pessoas')->select(['id', 'nome', 'nome_social'])->orderBy('id')->chunk(500, function ($pessoas): void {
            foreach ($pessoas as $pessoa) {
                DB::table('pessoas')->where('id', $pessoa->id)->update([
                    'nome_busca' => NormalizadorBusca::texto(trim($pessoa->nome.' '.($pessoa->nome_social ?? ''))),
                ]);
            }
        });

        DB::table('pessoa_contatos')->select(['id', 'tipo', 'valor'])->orderBy('id')->chunk(500, function ($contatos): void {
            foreach ($contatos as $contato) {
                DB::table('pessoa_contatos')->where('id', $contato->id)->update([
                    'valor_busca' => $contato->tipo === 'email'
                        ? mb_strtolower(trim($contato->valor))
                        : NormalizadorBusca::digitos($contato->valor),
                ]);
            }
        });

        if (DB::getDriverName() === 'pgsql' && DB::selectOne("select 1 as ok from pg_extension where extname = 'pg_trgm'") !== null) {
            DB::statement('create index if not exists pessoas_nome_busca_trgm_idx on pessoas using gin (nome_busca gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index if exists pessoas_nome_busca_trgm_idx');
        }

        Schema::table('pessoa_documentos', function (Blueprint $table) {
            $table->dropIndex(['implantacao_id', 'valor_normalizado']);
        });

        Schema::table('pessoa_contatos', function (Blueprint $table) {
            $table->dropIndex(['implantacao_id', 'valor_busca']);
            $table->dropColumn('valor_busca');
        });

        Schema::table('pessoas', function (Blueprint $table) {
            $table->dropIndex(['implantacao_id', 'nome_busca']);
            $table->dropColumn('nome_busca');
        });
    }
};

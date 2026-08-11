<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-002 (seção 10): "uma identidade de autenticação poderá ser associada
 * a uma ou mais implantações por relação explícita". `users` continua com
 * chave bigint (já referenciada por `sessions`, `pre_registration_edits`,
 * `imoveis.created_by/updated_by` — migrar para UUID agora seria uma
 * mudança arriscada e fora do escopo desta fatia), então a FK aqui usa o
 * mesmo tipo.
 *
 * Esta tabela é estrutural: `ImplantacaoContext::current()` continua
 * resolvendo para a implantação única semeada, sem consumir esta relação
 * ainda — resolução real de contexto por usuário logado é território de
 * P19/P20/P21, que ainda não existem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_implantacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('status')->default('ativa');
            $table->timestamps();

            $table->unique(['user_id', 'implantacao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_implantacoes');
    }
};

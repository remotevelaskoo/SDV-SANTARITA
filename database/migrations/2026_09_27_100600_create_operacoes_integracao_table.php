<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox e registro de idempotência das operações com equipamentos
 * (ADR-004 §18, ADR-005 §12 e §18, ADR-007 §7-10, docs/010 §16).
 *
 * `estado` é o ciclo da mensagem na outbox; `resultado` é o que se sabe
 * do efeito no equipamento. "Processado" nunca significa "abertura
 * confirmada": só `resultado = confirmado` tem esse sentido.
 * O payload é mínimo e jamais contém segredo, imagem ou template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operacoes_integracao', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->string('operacao', 60);
            $table->string('fila', 40);
            $table->string('chave_idempotencia', 190);
            $table->char('hash_payload', 64);
            $table->string('versao_contrato', 20);
            $table->json('payload');
            $table->string('agregado_tipo', 80)->nullable();
            $table->uuid('agregado_id')->nullable();
            $table->uuid('correlation_id');
            $table->uuid('causation_id')->nullable();
            $table->string('estado', 30)->default('pendente');
            $table->string('resultado', 40)->default('pendente');
            $table->unsignedInteger('tentativas')->default(0);
            $table->unsignedInteger('max_tentativas')->default(5);
            $table->dateTime('disponivel_em');
            $table->dateTime('lease_ate')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->dateTime('expira_em')->nullable();
            $table->dateTime('processado_em')->nullable();
            $table->string('erro_sanitizado', 500)->nullable();
            $table->json('resultado_dados')->nullable();
            $table->string('origem', 40)->default('sistema');
            $table->foreignId('ator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['implantacao_id', 'operacao', 'chave_idempotencia']);
            $table->index(['estado', 'disponivel_em']);
            $table->index(['equipamento_id', 'estado']);
            $table->index(['resultado', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacoes_integracao');
    }
};

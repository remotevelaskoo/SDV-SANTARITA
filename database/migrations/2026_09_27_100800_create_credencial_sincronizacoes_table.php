<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado explícito da distribuição de uma credencial a um equipamento
 * (docs/010 §14, docs/009 §21.6, RN-093).
 *
 * `credencial_id` é a referência lógica ao UUID interno da credencial. A
 * tabela `credenciais` ainda não existe na main; a chave estrangeira será
 * adicionada quando o módulo de credenciais for implementado (pendência
 * registrada em docs/015_FUNDACAO_INTEGRACAO_TERMINAIS_FACIAIS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credencial_sincronizacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->uuid('credencial_id');
            $table->string('estado', 40)->default('nao_enviado');
            $table->foreignUuid('ultima_operacao_id')->nullable()->constrained('operacoes_integracao')->nullOnDelete();
            $table->dateTime('ultima_tentativa_em')->nullable();
            $table->dateTime('sincronizado_em')->nullable();
            $table->dateTime('removido_em')->nullable();
            $table->string('erro_sanitizado', 500)->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();

            $table->unique(['equipamento_id', 'credencial_id']);
            $table->index(['implantacao_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credencial_sincronizacoes');
    }
};

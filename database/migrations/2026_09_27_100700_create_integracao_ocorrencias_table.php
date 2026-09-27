<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tentativas, erros e respostas sanitizadas das integrações (docs/010 §16).
 * Registro técnico somente de inserção; nunca guarda segredo, imagem
 * facial ou template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integracao_ocorrencias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->foreignUuid('operacao_integracao_id')->nullable()->constrained('operacoes_integracao')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->string('resultado', 40);
            $table->string('codigo', 80)->nullable();
            $table->string('mensagem_sanitizada', 500)->nullable();
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->dateTime('ocorrido_em');
            $table->timestamps();

            $table->index(['equipamento_id', 'ocorrido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integracao_ocorrencias');
    }
};

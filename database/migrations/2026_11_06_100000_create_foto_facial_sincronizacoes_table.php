<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado da foto da pessoa em cada terminal (AJ-010, ADR-013 1.1.0).
 *
 * Separado de `credencial_sincronizacoes`: o usuário gravado no terminal e a
 * face associada a ele são resultados distintos. A versão da foto é o
 * arquivo privado vigente e seu checksum; nenhuma imagem ou template fica
 * aqui. Foto nova invalida o "sincronizado" da versão anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foto_facial_sincronizacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->uuid('credencial_id');
            $table->foreignUuid('arquivo_id')->constrained('arquivos')->restrictOnDelete();
            $table->string('versao_foto', 64);
            $table->string('estado', 40)->default('nao_enviado');
            $table->foreignUuid('ultima_operacao_id')->nullable()->constrained('operacoes_integracao')->nullOnDelete();
            $table->dateTime('ultima_tentativa_em')->nullable();
            $table->dateTime('sincronizado_em')->nullable();
            $table->dateTime('removido_em')->nullable();
            $table->string('erro_sanitizado', 500)->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();

            $table->unique(['equipamento_id', 'credencial_id']);
            $table->index(['implantacao_id', 'pessoa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foto_facial_sincronizacoes');
    }
};

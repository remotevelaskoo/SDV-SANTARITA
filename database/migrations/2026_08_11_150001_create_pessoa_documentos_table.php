<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O valor do documento é guardado em texto simples nesta fatia — o mesmo
 * padrão já usado em pre_registrations.document. docs/010 pede "valor
 * cifrado ou protegido" (seção 8.1), mas a política de proteção de
 * documentos/imagens/biometria segue pendente (PEN-BDD-007), sem mecanismo,
 * gestão de chave ou ADR definidos. Decisão provisória registrada no PR;
 * cifrar exigirá revisitar esta migration quando PEN-BDD-007 for resolvida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoa_documentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->string('tipo');
            $table->string('pais', 2)->default('BR');
            $table->string('valor_normalizado');
            $table->string('valor_apresentacao');
            $table->string('status')->default('ativo');
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['implantacao_id', 'tipo', 'pais', 'valor_normalizado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa_documentos');
    }
};

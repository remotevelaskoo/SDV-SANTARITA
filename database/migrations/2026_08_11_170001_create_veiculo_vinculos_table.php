<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/010 (seção 11) lista pessoa, imóvel, empresa ou autorização como
 * alvos possíveis do vínculo veicular, e a seção 36 (PEN-BDD-011) deixa o
 * alvo principal como pendência aberta. Esta fatia escopa para pessoa
 * (obrigatória) + imóvel (contexto opcional), mesmo padrão de `vinculos`;
 * empresa e autorização ficam de fora até essas tabelas existirem de
 * verdade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculo_vinculos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignUuid('imovel_id')->nullable()->constrained('imoveis')->restrictOnDelete();
            $table->string('tipo');
            $table->string('status')->default('ativo');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();

            $table->index(['veiculo_id', 'status']);
            $table->index(['pessoa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculo_vinculos');
    }
};

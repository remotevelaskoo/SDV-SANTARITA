<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Concessão simples (perfil → permissão). Distinção entre concessão e
 * restrição/negação explícita fica para quando PEN-BDD-017
 * (precedência entre concessão e negação) for decidida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfil_permissoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('perfil_id')->constrained('perfis')->cascadeOnDelete();
            $table->foreignUuid('permissao_id')->constrained('permissoes')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['perfil_id', 'permissao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfil_permissoes');
    }
};

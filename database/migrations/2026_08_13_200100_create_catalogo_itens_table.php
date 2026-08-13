<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens de um catálogo, por implantação — cada implantação pode ter seus
 * próprios itens (RN-096: item já utilizado será inativado, não excluído).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('catalogo_id')->constrained('catalogos')->cascadeOnDelete();
            $table->string('codigo');
            $table->string('rotulo');
            $table->string('status')->default('ativo');
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['implantacao_id', 'catalogo_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_itens');
    }
};

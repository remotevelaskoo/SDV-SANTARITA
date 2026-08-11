<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operacional (ADR-002 seção 8.2 lista "perfis" explicitamente): cada
 * implantação define seus próprios perfis nomeados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('nome');
            $table->string('status')->default('ativo');
            $table->timestamps();

            $table->unique(['implantacao_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfis');
    }
};

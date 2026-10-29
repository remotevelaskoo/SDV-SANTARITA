<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto protegida da pessoa (AJ-006, fatia 1). Mesmo desenho de
 * `pre_registration_arquivos` (AJ-001): o binário fica no armazenamento
 * privado, `arquivos` guarda o catálogo e esta tabela liga a versão vigente
 * à pessoa. Substituir a foto cria nova versão; a anterior é preservada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoa_arquivos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignUuid('arquivo_id')->unique()->constrained('arquivos')->restrictOnDelete();
            $table->string('category', 40);
            $table->boolean('is_current')->default(true);
            $table->dateTime('linked_at');
            $table->dateTime('replaced_at')->nullable();
            $table->timestamps();

            $table->index(['pessoa_id', 'category', 'is_current'], 'pessoa_arquivos_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa_arquivos');
    }
};

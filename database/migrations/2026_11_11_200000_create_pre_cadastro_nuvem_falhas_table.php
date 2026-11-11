<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Falhas de importação do portal público: só códigos e nomes de campos,
     * nunca o conteúdo enviado (ADR-018, docs/023 §12).
     */
    public function up(): void
    {
        Schema::create('pre_cadastro_nuvem_falhas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->uuid('cloud_id');
            $table->string('protocol', 80)->nullable();
            $table->string('codigo', 40);
            $table->json('campos')->nullable();
            $table->unsignedInteger('tentativas')->default(0);
            // retentando | conferencia | reabertura_solicitada | resolvida
            $table->string('situacao', 30)->default('retentando');
            $table->timestamp('ultima_falha_em')->nullable();
            $table->foreignId('reabertura_solicitada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolvida_em')->nullable();
            $table->timestamps();
            $table->unique(['implantacao_id', 'cloud_id']);
            $table->index(['implantacao_id', 'situacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_cadastro_nuvem_falhas');
    }
};

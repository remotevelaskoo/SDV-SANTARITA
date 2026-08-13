<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override esparso: só existe uma linha quando alguém customiza uma
 * configuração para a própria implantação. Ausência de linha significa
 * "usando o valor padrão" (`configuracoes.valor_padrao`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implantacao_configuracoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('configuracao_id')->constrained('configuracoes')->cascadeOnDelete();
            $table->string('valor')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['implantacao_id', 'configuracao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implantacao_configuracoes');
    }
};

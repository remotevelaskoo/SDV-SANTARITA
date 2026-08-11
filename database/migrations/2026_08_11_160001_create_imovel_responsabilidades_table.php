<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deixada de fora do PR #22 (grupo Imóveis) por depender de vinculos, que
 * não existia ainda. Fecha essa lacuna agora que o grupo Vínculos existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imovel_responsabilidades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('imovel_id')->constrained('imoveis')->restrictOnDelete();
            $table->foreignUuid('vinculo_id')->constrained('vinculos')->restrictOnDelete();
            $table->string('tipo')->default('responsavel_principal');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index(['imovel_id', 'tipo', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imovel_responsabilidades');
    }
};

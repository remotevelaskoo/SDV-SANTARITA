<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capacidades efetivamente verificadas por equipamento (ADR-007 §6,
 * RN-091). Ausência de linha ou `suportada = false` significa que a
 * operação não é oferecida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamento_capacidades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->string('capacidade', 60);
            $table->boolean('suportada')->default(false);
            $table->string('origem', 40);
            $table->string('versao_contrato', 20);
            $table->string('firmware_versao', 80)->nullable();
            $table->string('motivo_ausencia')->nullable();
            $table->dateTime('verificada_em');
            $table->timestamps();

            $table->unique(['equipamento_id', 'capacidade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamento_capacidades');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A finalidade do endereço pessoal segue pendente (PEN-BDD-008 / PEN-RNG-006):
 * este endereço não substitui o endereço do imóvel (enderecos_imoveis) e não
 * será copiado para lá. A tabela existe estruturalmente; nada a popula ainda
 * nesta fatia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoa_enderecos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->string('finalidade');
            $table->string('zip_code');
            $table->string('address');
            $table->string('address_number');
            $table->string('address_complement')->nullable();
            $table->string('district');
            $table->string('city');
            $table->string('state', 2);
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index(['pessoa_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa_enderecos');
    }
};

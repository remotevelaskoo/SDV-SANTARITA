<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condominios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('nome');
            $table->string('codigo');
            $table->string('status')->default('ativo');
            $table->timestamps();

            $table->unique(['implantacao_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condominios');
    }
};

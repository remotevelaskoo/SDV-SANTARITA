<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('condominio_id')->constrained('condominios')->restrictOnDelete();
            $table->string('nome');
            $table->string('codigo')->nullable();
            $table->unsignedSmallInteger('ordem')->nullable();
            $table->string('status')->default('ativo');
            $table->timestamps();

            $table->unique(['condominio_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocos');
    }
};

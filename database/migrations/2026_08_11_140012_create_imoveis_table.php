<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imoveis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('condominio_id')->constrained('condominios')->restrictOnDelete();
            $table->foreignUuid('bloco_id')->nullable()->constrained('blocos')->restrictOnDelete();
            $table->string('codigo');
            $table->string('unidade');
            $table->string('tipo');
            $table->string('status')->default('ativo');
            $table->unsignedInteger('versao')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['implantacao_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imoveis');
    }
};

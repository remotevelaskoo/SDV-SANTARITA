<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('cnpj');
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('categoria');
            $table->string('status')->default('ativo');
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();

            $table->unique(['implantacao_id', 'cnpj']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};

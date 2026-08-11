<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('nome');
            $table->string('nome_social')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('status')->default('ativo');
            $table->unsignedInteger('versao')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoas');
    }
};

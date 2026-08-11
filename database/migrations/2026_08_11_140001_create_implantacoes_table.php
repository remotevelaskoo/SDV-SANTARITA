<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implantacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organizacao_id')->constrained('organizacoes')->restrictOnDelete();
            $table->string('nome');
            $table->string('slug')->nullable()->unique();
            $table->string('status')->default('ativa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implantacoes');
    }
};

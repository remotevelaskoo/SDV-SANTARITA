<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo global (ADR-002 seção 8.1: "catálogo técnico de permissões" é
 * um dos exemplos explícitos de entidade global) — o conjunto de ações
 * possíveis é definido pelo código, não por implantação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('chave')->unique();
            $table->string('modulo');
            $table->string('descricao');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissoes');
    }
};

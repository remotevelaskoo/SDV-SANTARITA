<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribuição temporal de perfil a usuário, sempre no escopo de uma
 * implantação — o mesmo usuário pode ter perfis diferentes em
 * implantações diferentes (ADR-002 seção 10.1: "permissões não serão
 * transportadas automaticamente entre implantações").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_perfis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('perfil_id')->constrained('perfis')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'implantacao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_perfis');
    }
};

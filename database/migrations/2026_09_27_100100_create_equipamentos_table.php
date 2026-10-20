<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equipamento cadastrado por implantação (docs/010 §16, docs/008 §22-23,
 * ADR-007 e ADR-016). Fabricante, modelo e firmware são inventário: o
 * comportamento vem do adaptador e das capacidades verificadas, nunca do
 * nome do fabricante. O vínculo com o ponto fica em tabela própria para
 * preservar histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('nome');
            $table->string('tipo', 40);
            $table->string('fabricante', 80);
            $table->string('modelo', 80);
            $table->string('numero_serie', 120)->nullable();
            $table->string('endereco_rede', 45);
            $table->unsignedInteger('porta_rede')->nullable();
            $table->string('protocolo', 40);
            $table->string('firmware_versao', 80)->nullable();
            $table->string('adaptador', 60);
            $table->string('direcao', 20);
            $table->string('status', 30)->default('nao_configurado');
            $table->string('estado_saude', 30)->default('desconhecido');
            $table->dateTime('ultima_comunicacao_at')->nullable();
            $table->dateTime('ultimo_teste_at')->nullable();
            $table->string('ultimo_erro_sanitizado', 500)->nullable();
            $table->unsignedInteger('timeout_segundos')->default(5);
            $table->unsignedInteger('versao')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('inactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['implantacao_id', 'numero_serie']);
            $table->index(['implantacao_id', 'status']);
            $table->index(['implantacao_id', 'estado_saude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamentos');
    }
};

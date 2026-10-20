<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ponto físico de acesso (docs/010 §15.1, docs/008 §21). No recorte da
 * ADR-016 o ponto é uma cancela ou catraca acionada pelo relé de um único
 * terminal facial. Ponto nunca é excluído fisicamente: é inativado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pontos_acesso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('codigo', 60);
            $table->string('nome');
            $table->string('tipo', 30);
            $table->string('direcao_suportada', 20);
            $table->string('localizacao')->nullable();
            $table->string('status', 30)->default('em_implantacao');
            $table->text('observacao')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('inactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['implantacao_id', 'codigo']);
            $table->index(['implantacao_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pontos_acesso');
    }
};

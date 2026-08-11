<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de domínio própria para o histórico de tentativas de acesso — não
 * é a estrutura genérica de auditoria do ADR-004 (auditoria_eventos), que
 * fica reservada para a P22. Decisão registrada em docs/013.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_acessos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->foreignUuid('imovel_id')->nullable()->constrained('imoveis')->nullOnDelete();
            $table->foreignUuid('veiculo_id')->nullable()->constrained('veiculos')->nullOnDelete();
            $table->string('ponto_acesso');
            $table->string('tipo');
            $table->string('resultado');
            $table->string('motivo_negacao')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('protocol')->unique();
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['tipo', 'resultado']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_acessos');
    }
};

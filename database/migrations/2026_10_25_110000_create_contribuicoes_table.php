<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contribuição registrada no atendimento e número do ticket de acesso
 * (AJ-006, fatia 2, docs/020 §3; docs/010 §18).
 *
 * - `ticket_numero` é texto: preserva zeros à esquerda e não presume série
 *   fiscal; a unicidade não é imposta, só alertada (`ticket_duplicado`);
 * - a contribuição pertence ao atendimento (`historico_acesso_id`) e nunca
 *   substitui autorização (docs/010 §15.3);
 * - a correção posterior do ticket não sobrescreve sem rastro: cada mudança
 *   fica em `contribuicao_ticket_correcoes` com o valor anterior, o novo, a
 *   justificativa e o operador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribuicoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('historico_acesso_id')->unique()->constrained('historico_acessos')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->string('classificacao', 20);
            $table->string('situacao_pagamento', 20);
            $table->decimal('valor', 10, 2)->nullable();
            $table->string('forma_pagamento', 20)->nullable();
            $table->string('ticket_numero', 30)->nullable();
            $table->boolean('ticket_duplicado')->default(false);
            $table->foreignUuid('caixa_turno_id')->nullable()->constrained('caixa_turnos')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('registrada_em');
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();

            $table->index(['implantacao_id', 'ticket_numero']);
            $table->index(['implantacao_id', 'situacao_pagamento', 'registrada_em']);
        });

        Schema::create('contribuicao_ticket_correcoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('contribuicao_id')->constrained('contribuicoes')->restrictOnDelete();
            $table->string('valor_anterior', 30)->nullable();
            $table->string('valor_novo', 30)->nullable();
            $table->string('justificativa', 300);
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('corrigido_em');
            $table->timestamps();

            $table->index(['contribuicao_id', 'corrigido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribuicao_ticket_correcoes');
        Schema::dropIfExists('contribuicoes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbox dos eventos recebidos ou coletados dos equipamentos (ADR-005 §19,
 * ADR-007 §13, ADR-016 §7). Deduplica pelo identificador externo do
 * evento, preserva o instante do equipamento separado do instante de
 * recebimento e não guarda imagem facial nem template. A correlação com
 * `eventos_acesso` do domínio fica para quando esse módulo existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamento_eventos_recebidos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->string('id_externo_evento', 190);
            $table->char('hash_payload', 64);
            $table->string('tipo', 60);
            $table->string('direcao', 20)->nullable();
            $table->string('resultado_equipamento', 40)->nullable();
            $table->string('referencia_credencial_externa', 190)->nullable();
            $table->dateTime('ocorrido_no_equipamento_em')->nullable();
            $table->dateTime('recebido_em');
            $table->integer('divergencia_relogio_segundos')->nullable();
            $table->json('dados_sanitizados')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamps();

            $table->unique(['equipamento_id', 'id_externo_evento']);
            $table->index(['implantacao_id', 'recebido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamento_eventos_recebidos');
    }
};

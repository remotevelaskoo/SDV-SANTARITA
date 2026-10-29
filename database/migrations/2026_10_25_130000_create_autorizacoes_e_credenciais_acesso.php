<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autorização de acesso e credencial distribuída aos terminais (AJ-006,
 * fatias 6 e 7).
 *
 * - a autorização é a decisão do SDV (RN-040) com início e fim; o turista
 *   recebe uma autorização nova a cada visita, de no máximo 24 horas, e a
 *   pessoa continua no SDV;
 * - a credencial é o que vai ao terminal, sempre ligada a uma autorização e
 *   com a mesma vigência. Não carrega imagem nem template: credencial facial
 *   continua bloqueada pelo ADR-013;
 * - nada é apagado: encerrar, expirar ou revogar só muda a situação e
 *   preenche a data e o motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autorizacoes_acesso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignUuid('vinculo_id')->nullable()->constrained('vinculos')->restrictOnDelete();
            $table->foreignUuid('imovel_id')->nullable()->constrained('imoveis')->restrictOnDelete();
            // vinculo = segue o vínculo com o imóvel; turista_24h = uma visita.
            $table->string('tipo', 30);
            $table->dateTime('inicio');
            $table->dateTime('fim')->nullable();
            // ativa, expirada, encerrada ou cancelada.
            $table->string('status', 20);
            $table->string('responsavel', 160)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('encerrada_em')->nullable();
            $table->string('motivo_encerramento', 200)->nullable();
            $table->foreignId('encerrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();

            $table->index(['implantacao_id', 'pessoa_id', 'status']);
            $table->index(['status', 'fim']);
        });

        Schema::create('credenciais_acesso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->foreignUuid('autorizacao_id')->constrained('autorizacoes_acesso')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->dateTime('vigencia_inicio');
            $table->dateTime('vigencia_fim')->nullable();
            // ativa, expirada ou revogada.
            $table->string('status', 20);
            $table->dateTime('revogada_em')->nullable();
            $table->timestamps();

            $table->unique(['autorizacao_id', 'tipo']);
            $table->index(['implantacao_id', 'pessoa_id']);
            $table->index(['status', 'vigencia_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credenciais_acesso');
        Schema::dropIfExists('autorizacoes_acesso');
    }
};

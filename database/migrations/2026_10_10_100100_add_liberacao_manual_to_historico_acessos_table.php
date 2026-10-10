<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evento de acesso da liberação manual operacional (ADR-017).
 *
 * `resultado` continua sendo a decisão da portaria (liberado, negado,
 * pendente). O efeito no equipamento fica separado em `resultado_tecnico`
 * (solicitada, enviada, aceita, recusada, desconhecida, falha, expirada),
 * porque "aceito pelo terminal" não comprova a passagem física.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historico_acessos', function (Blueprint $table) {
            $table->string('origem', 40)->default('validacao')->after('resultado');
            $table->boolean('excepcional')->default(false)->after('origem');
            $table->string('identificacao_excepcional', 200)->nullable()->after('excepcional');
            $table->text('justificativa_excepcional')->nullable()->after('identificacao_excepcional');
            $table->string('motivo', 500)->nullable()->after('justificativa_excepcional');
            $table->foreignUuid('posto_id')->nullable()->after('motivo')->constrained('postos')->nullOnDelete();
            $table->foreignUuid('ponto_acesso_id')->nullable()->after('posto_id')->constrained('pontos_acesso')->nullOnDelete();
            $table->foreignUuid('equipamento_id')->nullable()->after('ponto_acesso_id')->constrained('equipamentos')->nullOnDelete();
            $table->foreignUuid('operacao_integracao_id')->nullable()->after('equipamento_id')->constrained('operacoes_integracao')->nullOnDelete();
            $table->string('resultado_tecnico', 40)->nullable()->after('operacao_integracao_id');
            $table->uuid('correlation_id')->nullable()->after('resultado_tecnico');
            $table->string('chave_idempotencia', 120)->nullable()->unique()->after('correlation_id');

            $table->index(['origem', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('historico_acessos', function (Blueprint $table) {
            $table->dropIndex(['origem', 'occurred_at']);
            $table->dropUnique(['chave_idempotencia']);
            $table->dropConstrainedForeignId('operacao_integracao_id');
            $table->dropConstrainedForeignId('equipamento_id');
            $table->dropConstrainedForeignId('ponto_acesso_id');
            $table->dropConstrainedForeignId('posto_id');
            $table->dropColumn([
                'origem', 'excepcional', 'identificacao_excepcional', 'justificativa_excepcional',
                'motivo', 'resultado_tecnico', 'correlation_id', 'chave_idempotencia',
            ]);
        });
    }
};

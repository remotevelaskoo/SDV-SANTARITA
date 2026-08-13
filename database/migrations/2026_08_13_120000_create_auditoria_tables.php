<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria_eventos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->dateTime('recorded_at');
            $table->string('actor_type', 40);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_profile')->nullable();
            $table->string('session_hash', 64)->nullable();
            $table->string('action', 100);
            $table->string('module', 80);
            $table->string('entity_type', 160);
            $table->string('entity_id', 100)->nullable();
            $table->string('origin', 60)->default('sistema');
            $table->string('result', 30)->default('sucesso');
            $table->string('reason_code', 100)->nullable();
            $table->text('justification')->nullable();
            $table->uuid('correlation_id');
            $table->uuid('causation_id')->nullable();
            $table->string('classification', 30)->default('interna');

            $table->index(['implantacao_id', 'occurred_at']);
            $table->index(['module', 'action']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index(['result', 'occurred_at']);
        });

        Schema::create('auditoria_alteracoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auditoria_evento_id')->constrained('auditoria_eventos')->restrictOnDelete();
            $table->string('field_name');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('classification', 30)->default('interna');
            $table->boolean('is_masked')->default(false);

            $table->index(['auditoria_evento_id', 'field_name']);
        });

        Schema::create('auditoria_contextos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auditoria_evento_id')->unique()->constrained('auditoria_eventos')->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_method', 12)->nullable();
            $table->text('request_path')->nullable();
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_contextos');
        Schema::dropIfExists('auditoria_alteracoes');
        Schema::dropIfExists('auditoria_eventos');
    }
};

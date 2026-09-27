<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo temporal entre equipamento e ponto de acesso (ADR-016 §5,
 * CA-ADR-016-001). Encerrar um vínculo preenche `ended_at`; nada é apagado.
 *
 * Os índices únicos parciais garantem, no próprio banco, que um equipamento
 * tenha no máximo um ponto vigente e que um ponto tenha no máximo um
 * equipamento vigente. PostgreSQL e SQLite suportam índice parcial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamento_ponto_vinculos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->foreignUuid('ponto_acesso_id')->constrained('pontos_acesso')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('motivo_encerramento')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['implantacao_id', 'ponto_acesso_id']);
        });

        DB::statement('CREATE UNIQUE INDEX equipamento_ponto_vinculos_equipamento_vigente_unique ON equipamento_ponto_vinculos (equipamento_id) WHERE ended_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX equipamento_ponto_vinculos_ponto_vigente_unique ON equipamento_ponto_vinculos (ponto_acesso_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamento_ponto_vinculos');
    }
};

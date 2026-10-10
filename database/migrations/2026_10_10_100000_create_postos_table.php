<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Postos (guaritas) da portaria e o que cada um pode acionar (ADR-017).
 *
 * - `postos`: guarita ou posto de trabalho da implantação;
 * - `posto_pontos_acesso`: pontos de acesso que o posto atende. O
 *   equipamento permitido é o terminal vigente de cada ponto (vínculo
 *   exclusivo terminal ↔ ponto do ADR-016);
 * - `operador_postos`: em quais postos cada operador atua, com início e fim,
 *   para trocar operadores de função sem acesso global aos equipamentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('codigo', 60);
            $table->string('nome');
            $table->string('status', 20)->default('ativo');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['implantacao_id', 'codigo']);
        });

        Schema::create('posto_pontos_acesso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('posto_id')->constrained('postos')->restrictOnDelete();
            $table->foreignUuid('ponto_acesso_id')->constrained('pontos_acesso')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['posto_id', 'ended_at']);
        });
        DB::statement('CREATE UNIQUE INDEX posto_pontos_acesso_vigente_unique ON posto_pontos_acesso (posto_id, ponto_acesso_id) WHERE ended_at IS NULL');

        Schema::create('operador_postos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('posto_id')->constrained('postos')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
        });
        DB::statement('CREATE UNIQUE INDEX operador_postos_vigente_unique ON operador_postos (user_id, posto_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('operador_postos');
        Schema::dropIfExists('posto_pontos_acesso');
        Schema::dropIfExists('postos');
    }
};

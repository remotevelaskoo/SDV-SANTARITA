<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Postos operacionais (SDV-OPE-018, ADR-017 1.1.0).
 *
 * - `postos.tipo`: principal (alcança todos os relés autorizados),
 *   veiculos ou pedestres (priorizam seus pontos);
 * - `operador_turnos`: posto assumido pelo operador no turno, com início,
 *   término e motivo do término (troca de posto, encerramento, saída);
 * - `historico_acessos`: turno da liberação e se o relé era do posto ativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postos', function (Blueprint $table) {
            $table->string('tipo', 20)->default('principal')->after('nome');
        });

        Schema::create('operador_turnos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('posto_id')->constrained('postos')->restrictOnDelete();
            $table->foreignUuid('turno_anterior_id')->nullable()->constrained('operador_turnos')->nullOnDelete();
            $table->dateTime('iniciado_em');
            $table->dateTime('encerrado_em')->nullable();
            $table->string('motivo_encerramento', 30)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'encerrado_em']);
        });
        DB::statement('CREATE UNIQUE INDEX operador_turnos_ativo_unique ON operador_turnos (implantacao_id, user_id) WHERE encerrado_em IS NULL');

        Schema::table('historico_acessos', function (Blueprint $table) {
            $table->foreignUuid('operador_turno_id')->nullable()->after('posto_id')->constrained('operador_turnos')->nullOnDelete();
            $table->boolean('fora_do_posto')->default(false)->after('operador_turno_id');
        });
    }

    public function down(): void
    {
        Schema::table('historico_acessos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operador_turno_id');
            $table->dropColumn('fora_do_posto');
        });
        Schema::dropIfExists('operador_turnos');
        Schema::table('postos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};

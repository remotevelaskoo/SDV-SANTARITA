<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Referência protegida à credencial técnica do equipamento (docs/010 §16,
 * ADR-009 §6 e §15). A tabela guarda somente a REFERÊNCIA ao segredo
 * (ex.: `env:SDV_EQUIP_PORTARIA_01`), nunca o valor. Substituir cria nova
 * linha e marca a anterior como substituída.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamento_credenciais', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('equipamento_id')->constrained('equipamentos')->restrictOnDelete();
            $table->string('finalidade', 40)->default('administracao');
            $table->string('usuario_tecnico', 80)->nullable();
            $table->string('referencia_segredo', 200);
            $table->string('status', 20)->default('ativa');
            $table->dateTime('definida_em');
            $table->dateTime('substituida_em')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX equipamento_credenciais_ativa_unique ON equipamento_credenciais (equipamento_id, finalidade) WHERE status = 'ativa'");
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamento_credenciais');
    }
};

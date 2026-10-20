<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mapeamento entre entidade interna (UUID SDV) e identificador de
 * fabricante (docs/010 §16.1, ADR-007 §11, RN-090). O ID externo é sempre
 * string, secundário e com escopo de implantação, adaptador e tipo.
 * Substituição preserva a linha anterior com `substituida_em`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referencias_externas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('adaptador', 60);
            $table->foreignUuid('equipamento_id')->nullable()->constrained('equipamentos')->restrictOnDelete();
            $table->string('entidade_tipo', 80);
            $table->uuid('entidade_id');
            $table->string('tipo_externo', 80);
            $table->string('id_externo', 190);
            $table->dateTime('substituida_em')->nullable();
            $table->timestamps();

            $table->index(['entidade_tipo', 'entidade_id']);
        });

        DB::statement('CREATE UNIQUE INDEX referencias_externas_vigente_unique ON referencias_externas (implantacao_id, adaptador, tipo_externo, id_externo) WHERE substituida_em IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('referencias_externas');
    }
};

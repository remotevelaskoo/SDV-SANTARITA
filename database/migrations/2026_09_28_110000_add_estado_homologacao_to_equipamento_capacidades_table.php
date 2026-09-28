<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriz de homologação por capacidade: além de `suportada` (executável),
 * cada capacidade guarda o seu estado para o firmware verificado
 * (não implementada, não suportada, detectada, em homologação, homologada
 * ou bloqueada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipamento_capacidades', function (Blueprint $table) {
            $table->string('estado_homologacao', 30)->nullable()->after('suportada');
        });
    }

    public function down(): void
    {
        Schema::table('equipamento_capacidades', function (Blueprint $table) {
            $table->dropColumn('estado_homologacao');
        });
    }
};

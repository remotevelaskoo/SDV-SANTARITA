<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teste individual de relés (ADR-016 §21): cada terminal guarda a porta
 * (relé) que aciona e o registro de homologação do seu próprio relé.
 * A porta 1 continua sendo o padrão do DS-K1T673DX-BR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipamentos', function (Blueprint $table) {
            $table->unsignedSmallInteger('porta_rele')->default(1)->after('modulo_seguro_rs485');
            $table->dateTime('rele_homologado_em')->nullable()->after('porta_rele');
            $table->foreignId('rele_homologado_por')->nullable()->after('rele_homologado_em')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rele_homologado_por');
            $table->dropColumn(['porta_rele', 'rele_homologado_em']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Primeira integração real com o terminal facial em bancada (ADR-016 §11).
 *
 * - `esquema`: HTTP ou HTTPS de acesso ao terminal, configurável por equipamento;
 * - `tls_pin_sha256`: hash SHA-256 (base64) da chave pública do certificado
 *   confiado pelo operador. Usado somente pelo adaptador para aceitar um
 *   certificado autoassinado sem desligar a validação TLS globalmente;
 * - `modulo_seguro_rs485`: o relé é acionado por módulo seguro externo;
 * - `ultima_falha_at`: instante da falha técnica mais recente.
 *
 * A senha técnica passa a poder ser gravada cifrada com a chave da
 * aplicação (`segredo_cifrado`), nunca em texto claro. A referência
 * `env:NOME` continua aceita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipamentos', function (Blueprint $table) {
            $table->string('esquema', 5)->default('https')->after('porta_rede');
            $table->string('tls_pin_sha256', 64)->nullable()->after('esquema');
            $table->boolean('modulo_seguro_rs485')->default(false)->after('direcao');
            $table->dateTime('ultima_falha_at')->nullable()->after('ultimo_erro_sanitizado');
        });

        Schema::table('equipamento_credenciais', function (Blueprint $table) {
            $table->text('segredo_cifrado')->nullable()->after('referencia_segredo');
        });
    }

    public function down(): void
    {
        Schema::table('equipamento_credenciais', function (Blueprint $table) {
            $table->dropColumn('segredo_cifrado');
        });

        Schema::table('equipamentos', function (Blueprint $table) {
            $table->dropColumn(['esquema', 'tls_pin_sha256', 'modulo_seguro_rs485', 'ultima_falha_at']);
        });
    }
};

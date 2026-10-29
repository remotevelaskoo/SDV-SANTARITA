<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No pré-cadastro só nome e CPF são obrigatórios (decisão do PO em
 * 28/10/2026). Nascimento, telefone, e-mail e endereço passam a opcionais;
 * documento e selfie continuam exigidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_registrations', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->text('address_informed')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pre_registrations', function (Blueprint $table) {
            $table->date('birth_date')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->text('address_informed')->nullable(false)->change();
        });
    }
};

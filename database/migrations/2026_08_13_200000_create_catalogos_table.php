<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo global (mesmo espírito de `permissoes`/`configuracoes`): a
 * definição de QUAIS catálogos existem é fixa pelo código, não por
 * implantação. Os itens de cada catálogo (o conteúdo de fato) vivem em
 * `catalogo_itens`, por implantação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('chave')->unique();
            $table->string('rotulo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo global de configurações (mesmo espírito de `permissoes`): o
 * conjunto de chaves configuráveis é definido pelo código/seeder, não por
 * implantação. O valor por implantação vive em `implantacao_configuracoes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('chave')->unique();
            $table->string('categoria');
            $table->string('tipo', 20);
            $table->string('rotulo');
            $table->string('descricao')->nullable();
            $table->string('valor_padrao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};

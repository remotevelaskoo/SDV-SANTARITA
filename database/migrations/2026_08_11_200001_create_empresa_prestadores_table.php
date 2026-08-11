<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_prestadores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->string('atividade');
            $table->string('status')->default('ativo');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index(['pessoa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_prestadores');
    }
};

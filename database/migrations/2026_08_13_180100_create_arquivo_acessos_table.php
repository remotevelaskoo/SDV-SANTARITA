<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trilha de auditoria escopada a abertura de arquivo protegido
        // (RN-046 a RN-049, docs/010 linha 901) — não o P22 genérico, que
        // ainda não existe (mesmo precedente já usado para HistoricoAcesso
        // antes do P22).
        Schema::create('arquivo_acessos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes');
            $table->foreignUuid('arquivo_id')->constrained('arquivos');
            $table->foreignId('ator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contexto');
            $table->string('resultado');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivo_acessos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Corte desta fatia (ver docs/013 §8.1 AJ-001 e ADR-006): disco local
        // privado (storage/app/private, já não-público no Laravel 13) em vez
        // do S3-compatível aprovado no ADR-006, que exige um fornecedor ainda
        // não escolhido. A chave em `caminho` já segue o template opaco do
        // ADR-006 §12 (implantação/categoria/ano/mes/uuid, sem dado pessoal),
        // então trocar de disco depois é config, não reescrita.
        Schema::create('arquivos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes');
            $table->uuidMorphs('fileable');
            $table->string('categoria');
            $table->string('disco');
            $table->string('caminho');
            $table->string('nome_original');
            $table->string('mime');
            $table->unsignedInteger('tamanho');
            $table->string('checksum');
            $table->string('estado')->default('disponivel');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivos');
    }
};

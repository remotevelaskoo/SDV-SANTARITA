<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoa_contatos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pessoa_id')->constrained('pessoas')->restrictOnDelete();
            $table->string('tipo');
            $table->string('valor');
            $table->boolean('principal')->default(false);
            $table->boolean('verificado')->default(false);
            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index(['pessoa_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa_contatos');
    }
};

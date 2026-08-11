<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('plate_display');
            $table->string('plate_normalized');
            $table->string('country', 2)->default('BR');
            $table->string('type');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->string('status')->default('ativo');
            $table->timestamps();

            $table->unique(['implantacao_id', 'plate_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos');
    }
};

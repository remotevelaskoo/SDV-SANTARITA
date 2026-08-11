<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enderecos_imoveis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('imovel_id')->constrained('imoveis')->restrictOnDelete();
            $table->string('zip_code');
            $table->string('address');
            $table->string('address_number');
            $table->string('address_complement')->nullable();
            $table->string('district');
            $table->string('city');
            $table->string('state', 2);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index(['imovel_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enderecos_imoveis');
    }
};

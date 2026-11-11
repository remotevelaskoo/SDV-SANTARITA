<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pre_cadastro_nuvem_recebimentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->uuid('cloud_id');
            $table->foreignUuid('pre_registration_id')->constrained('pre_registrations')->restrictOnDelete();
            $table->unsignedInteger('acked_version')->default(0);
            $table->unique(['implantacao_id', 'cloud_id']);
            $table->unique('pre_registration_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_cadastro_nuvem_recebimentos');
    }
};

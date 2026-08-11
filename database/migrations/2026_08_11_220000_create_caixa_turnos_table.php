<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caixa_turnos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('terminal');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->decimal('opening_balance', 10, 2);
            $table->dateTime('closed_at')->nullable();
            $table->decimal('informed_amount', 10, 2)->nullable();
            $table->decimal('expected_amount', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->text('closing_notes')->nullable();
            $table->string('status')->default('aberto');
            $table->string('closing_status')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_turnos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caixa_movimentacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('caixa_turno_id')->constrained('caixa_turnos')->restrictOnDelete();
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('method');
            $table->string('description');
            $table->string('protocol')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['caixa_turno_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_movimentacoes');
    }
};

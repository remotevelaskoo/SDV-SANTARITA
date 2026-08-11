<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encomendas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('protocol')->unique();
            $table->string('recipient_name');
            $table->foreignUuid('imovel_id')->constrained('imoveis')->restrictOnDelete();
            $table->string('carrier');
            $table->string('type');
            $table->string('storage_location');
            $table->string('status')->default('aguardando');
            $table->dateTime('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->string('delivered_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encomendas');
    }
};

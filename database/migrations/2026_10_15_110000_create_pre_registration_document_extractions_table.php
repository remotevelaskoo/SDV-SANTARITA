<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_registration_document_extractions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pre_registration_id')->constrained('pre_registrations')->restrictOnDelete();
            $table->string('document_type', 20);
            $table->string('engine', 80);
            $table->string('model_version', 120);
            $table->string('status', 30);
            $table->longText('extracted_fields');
            $table->longText('field_confidences');
            $table->longText('confirmed_fields');
            $table->dateTime('processed_at');
            $table->dateTime('confirmed_at');
            $table->timestamps();

            $table->index(['pre_registration_id', 'processed_at'], 'pre_registration_extractions_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_registration_document_extractions');
    }
};

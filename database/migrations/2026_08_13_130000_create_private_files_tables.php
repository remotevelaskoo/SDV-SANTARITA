<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arquivos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->string('disk', 80);
            $table->string('object_key', 500)->unique();
            $table->text('original_name');
            $table->string('display_name');
            $table->string('extension', 12);
            $table->string('declared_mime', 120)->nullable();
            $table->string('detected_mime', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('classification', 30);
            $table->string('purpose', 80);
            $table->string('status', 40);
            $table->string('origin', 60);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('retention_rule')->nullable();
            $table->dateTime('retention_eligible_at')->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['implantacao_id', 'status']);
            $table->index(['purpose', 'status']);
            $table->index('checksum_sha256');
        });

        Schema::create('pre_registration_arquivos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('implantacao_id')->constrained('implantacoes')->restrictOnDelete();
            $table->foreignUuid('pre_registration_id')->constrained('pre_registrations')->restrictOnDelete();
            $table->foreignUuid('arquivo_id')->unique()->constrained('arquivos')->restrictOnDelete();
            $table->string('category', 40);
            $table->boolean('is_current')->default(true);
            $table->dateTime('linked_at');
            $table->dateTime('replaced_at')->nullable();
            $table->timestamps();

            $table->index(['pre_registration_id', 'category', 'is_current'], 'pre_registration_arquivos_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_registration_arquivos');
        Schema::dropIfExists('arquivos');
    }
};

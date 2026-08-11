<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_registration_edits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pre_registration_id')->constrained('pre_registrations')->restrictOnDelete();

            $table->string('action');
            $table->string('field');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason');
            $table->string('result')->default('sucesso');

            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operator_name');

            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pre_registration_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_registration_edits');
    }
};

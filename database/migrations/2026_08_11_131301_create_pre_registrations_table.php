<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('protocol')->unique();

            $table->string('name');
            $table->string('document');
            $table->date('birth_date');
            $table->string('phone');
            $table->string('email');

            $table->string('access_type');
            $table->text('address_informed');

            $table->string('destination_property')->nullable();
            $table->string('destination_label');
            $table->string('responsible_name')->nullable();

            $table->dateTime('period_start');
            $table->dateTime('period_end');

            $table->string('vehicle_plate')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_color')->nullable();

            $table->string('document_status');
            $table->string('selfie_status');

            $table->string('status')->default('aguardando');
            $table->string('alert')->nullable();

            $table->dateTime('submitted_at');
            $table->dateTime('status_changed_at')->nullable();

            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_registrations');
    }
};

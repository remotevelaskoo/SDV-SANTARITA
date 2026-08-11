<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha a lacuna registrada no PR #21: pre_registrations e
 * pre_registration_edits são entidades operacionais (ADR-002, seção 8.2,
 * lista "pré-cadastros" explicitamente) e precisam de implantacao_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_registrations', function (Blueprint $table) {
            $table->foreignUuid('implantacao_id')->nullable()->after('id')->constrained('implantacoes')->restrictOnDelete();
        });

        Schema::table('pre_registration_edits', function (Blueprint $table) {
            $table->foreignUuid('implantacao_id')->nullable()->after('id')->constrained('implantacoes')->restrictOnDelete();
        });

        $implantacaoId = DB::table('implantacoes')->value('id');

        if ($implantacaoId !== null) {
            DB::table('pre_registrations')->update(['implantacao_id' => $implantacaoId]);
            DB::table('pre_registration_edits')->update(['implantacao_id' => $implantacaoId]);
        }

        Schema::table('pre_registrations', function (Blueprint $table) {
            $table->uuid('implantacao_id')->nullable(false)->change();
        });

        Schema::table('pre_registration_edits', function (Blueprint $table) {
            $table->uuid('implantacao_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pre_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('implantacao_id');
        });

        Schema::table('pre_registration_edits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('implantacao_id');
        });
    }
};

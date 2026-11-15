<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decisões do PO (15/11/2026):
 * - o administrador cria o colaborador com uma senha inicial, que é trocada
 *   no primeiro acesso (o servidor é local e não manda e-mail de convite);
 * - o acesso ao painel de gestão na nuvem é marcado por usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('trocar_senha')->default(false);
            $table->boolean('acesso_painel_remoto')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['trocar_senha', 'acesso_painel_remoto']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bancos criados antes do ConfiguracaoSeeder ter a chave não têm a
 * configuração "Valor da contribuição por acesso", e a migração anterior
 * só atualizava a linha existente. Aqui ela é criada quando falta, com o
 * valor fixo de R$ 25,00 (só turista contribui; o administrador pode mudar
 * em Configurações). Migração separada para também valer em bancos que já
 * rodaram 2026_11_11_140000.
 */
return new class extends Migration
{
    private const CHAVE = 'contribuicao.valor_padrao';

    public function up(): void
    {
        if (DB::table('configuracoes')->where('chave', self::CHAVE)->exists()) {
            return;
        }

        DB::table('configuracoes')->insert([
            'id' => (string) Str::uuid7(),
            'chave' => self::CHAVE,
            'categoria' => 'contribuição e caixa',
            'tipo' => 'numero',
            'rotulo' => 'Valor da contribuição por acesso',
            'descricao' => 'Valor apresentado na Validação de Entrada quando o turista contribui. Só turista contribui (regra do PO de 11/11/2026).',
            'valor_padrao' => '25.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sem volta: a configuração pode ter sido ajustada pelo administrador.
    }
};

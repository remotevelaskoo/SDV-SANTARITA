<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regra do PO (11/11/2026): só turista contribui e o valor é R$ 25,00.
 * Atualiza o valor padrão da configuração que ainda estiver no antigo
 * R$ 15,00; um valor ajustado pela implantação não é tocado.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuracoes')
            ->where('chave', 'contribuicao.valor_padrao')
            ->where('valor_padrao', '15.00')
            ->update([
                'valor_padrao' => '25.00',
                'descricao' => 'Valor apresentado na Validação de Entrada quando o turista contribui. Só turista contribui (regra do PO de 11/11/2026).',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('configuracoes')
            ->where('chave', 'contribuicao.valor_padrao')
            ->where('valor_padrao', '25.00')
            ->update(['valor_padrao' => '15.00', 'updated_at' => now()]);
    }
};

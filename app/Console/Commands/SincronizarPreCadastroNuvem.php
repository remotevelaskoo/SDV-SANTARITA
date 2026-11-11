<?php

namespace App\Console\Commands;

use App\Services\Hibrido\PreCadastroNuvem;
use Illuminate\Console\Command;

class SincronizarPreCadastroNuvem extends Command
{
    protected $signature = 'pre-cadastro:sincronizar-nuvem';

    protected $description = 'Busca pré-cadastros públicos e confirma recebimento e situação sem liberar acesso.';

    public function handle(PreCadastroNuvem $sync): int
    {
        try {
            $totais = $sync->sincronizar();
            $this->info(sprintf('Recebidos: %d; confirmações: %d; pendências: %d.', $totais['importados'], $totais['confirmados'], $totais['falhas']));

            return $totais['falhas'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Sincronização não concluída. Confira conectividade e configuração; os registros locais foram preservados.');

            return self::FAILURE;
        }
    }
}

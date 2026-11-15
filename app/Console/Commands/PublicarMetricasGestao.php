<?php

namespace App\Console\Commands;

use App\Services\Gestao\PainelGestaoNuvem;
use Illuminate\Console\Command;

class PublicarMetricasGestao extends Command
{
    protected $signature = 'gestao:publicar-metricas';

    protected $description = 'Envia os totais do dia (arrecadação, caixas, acessos e cadastros) ao painel de gestão na nuvem.';

    public function handle(PainelGestaoNuvem $painel): int
    {
        try {
            $this->info('Publicado: '.implode(', ', $painel->publicar()).'.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Painel de gestão não atualizado: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

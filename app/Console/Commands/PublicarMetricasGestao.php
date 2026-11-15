<?php

namespace App\Console\Commands;

use App\Services\Gestao\PainelGestaoNuvem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PublicarMetricasGestao extends Command
{
    protected $signature = 'gestao:publicar-metricas {--desde= : Reenvia também cada dia desde esta data (AAAA-MM-DD), até 93 dias}';

    protected $description = 'Envia os totais do dia (arrecadação, caixas, acessos e cadastros) ao painel de gestão na nuvem.';

    public function handle(PainelGestaoNuvem $painel): int
    {
        $desde = $this->option('desde');
        if ($desde !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $desde)) {
            $this->error('Use a data no formato AAAA-MM-DD, por exemplo 2026-11-01.');

            return self::FAILURE;
        }

        try {
            $dias = $painel->publicar($desde ? Carbon::parse($desde) : null);
            $this->info('Publicado: '.(count($dias) > 4 ? reset($dias).' a '.end($dias).' ('.count($dias).' dias)' : implode(', ', $dias)).'.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Painel de gestão não atualizado: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

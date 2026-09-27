<?php

namespace App\Console\Commands;

use App\Integracoes\Aplicacao\DespachanteOutbox;
use Illuminate\Console\Command;

class DespacharOutboxIntegracao extends Command
{
    protected $signature = 'integracoes:despachar {--limite= : Quantidade máxima de operações por execução}';

    protected $description = 'Republica operações de integração pendentes, com retentativa vencida ou lease expirado';

    public function handle(DespachanteOutbox $despachante): int
    {
        $total = $despachante->despachar($this->option('limite') !== null ? (int) $this->option('limite') : null);
        $this->info("{$total} operação(ões) publicada(s).");

        return self::SUCCESS;
    }
}

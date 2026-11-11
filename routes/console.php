<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// AJ-006, fatia 7: vencimento das visitas de turista e revogação nos terminais.
// Só roda com `schedule:run` no cron do servidor. A retentativa dos pedidos
// depende também de `integracoes:despachar`, que segue sem agendamento até
// haver worker em produção (docs/015).
Schedule::command('acessos:processar-vencimentos')->everyMinute()->withoutOverlapping();

// ADR-018: somente conexões de saída. Desligado até configurar o portal isolado.
Schedule::command('pre-cadastro:sincronizar-nuvem')
    ->everyMinute()->withoutOverlapping(60)
    ->when(fn () => (bool) config('pre_cadastro_nuvem.enabled'));

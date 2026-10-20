<?php

namespace App\Console\Commands;

use App\Integracoes\Aplicacao\Reconciliador;
use App\Models\Equipamento;
use App\Models\Implantacao;
use App\Support\ImplantacaoContext;
use Illuminate\Console\Command;

class ReconciliarEquipamentos extends Command
{
    protected $signature = 'integracoes:reconciliar';

    protected $description = 'Agenda consultas de reconciliação para sincronizações e comandos com resultado desconhecido';

    public function handle(Reconciliador $reconciliador): int
    {
        $consultas = $intervencoes = 0;

        Implantacao::query()->where('status', 'ativa')->each(function (Implantacao $implantacao) use ($reconciliador, &$consultas, &$intervencoes) {
            ImplantacaoContext::executarNo($implantacao, function () use ($reconciliador, &$consultas, &$intervencoes) {
                Equipamento::query()->where('status', '!=', 'inativo')->each(function (Equipamento $equipamento) use ($reconciliador, &$consultas, &$intervencoes) {
                    $resultado = $reconciliador->reconciliar($equipamento);
                    $consultas += $resultado['consultas'];
                    $intervencoes += $resultado['intervencoes'];
                });
            });
        });

        $this->info("{$consultas} consulta(s) agendada(s); {$intervencoes} pendência(s) enviada(s) para intervenção.");

        return self::SUCCESS;
    }
}

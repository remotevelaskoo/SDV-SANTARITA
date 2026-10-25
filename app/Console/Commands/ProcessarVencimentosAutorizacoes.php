<?php

namespace App\Console\Commands;

use App\Acesso\Aplicacao\AutorizacoesAcesso;
use App\Models\Implantacao;
use App\Support\ImplantacaoContext;
use Illuminate\Console\Command;

/**
 * Expira autorizações vencidas (turista 24h) e pede a remoção nos
 * terminais, repetindo o pedido enquanto o terminal não confirmar
 * (AJ-006, fatia 7). Pode rodar a cada minuto: é idempotente.
 */
class ProcessarVencimentosAutorizacoes extends Command
{
    protected $signature = 'acessos:processar-vencimentos';

    protected $description = 'Expira autorizações vencidas e pede a revogação das credenciais nos terminais';

    public function handle(AutorizacoesAcesso $autorizacoes): int
    {
        $expiradas = $revogacoes = 0;

        Implantacao::query()->where('status', 'ativa')->each(function (Implantacao $implantacao) use ($autorizacoes, &$expiradas, &$revogacoes): void {
            $resultado = ImplantacaoContext::executarNo($implantacao, fn () => $autorizacoes->processarVencimentos());
            $expiradas += $resultado['expiradas'];
            $revogacoes += $resultado['revogacoes'];
        });

        $this->info("{$expiradas} autorização(ões) expirada(s); {$revogacoes} pedido(s) de revogação registrado(s).");

        return self::SUCCESS;
    }
}

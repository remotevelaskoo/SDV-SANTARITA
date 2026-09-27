<?php

namespace App\Jobs;

use App\Integracoes\Aplicacao\ProcessadorOperacoes;
use App\Models\Implantacao;
use App\Support\ImplantacaoContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Transporta somente identificadores (ADR-005 §15): o worker recarrega o
 * estado no contexto da implantação da operação. A fila entrega ao menos
 * uma vez; a reserva com lease no processador evita efeito duplicado.
 */
class ProcessarOperacaoIntegracao implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $implantacaoId, public string $operacaoId) {}

    public function handle(ProcessadorOperacoes $processador): void
    {
        $implantacao = Implantacao::query()->where('status', 'ativa')->find($this->implantacaoId);
        if ($implantacao === null) {
            return;
        }

        ImplantacaoContext::executarNo($implantacao, fn () => $processador->processar($this->operacaoId));
    }
}

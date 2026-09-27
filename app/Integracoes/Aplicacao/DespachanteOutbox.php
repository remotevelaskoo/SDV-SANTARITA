<?php

namespace App\Integracoes\Aplicacao;

use App\Integracoes\Dominio\Enums\EstadoOutbox;
use App\Models\OperacaoIntegracao as Operacao;
use App\Models\Scopes\ImplantacaoScope;

/**
 * Torna a outbox a origem reconciliável (ADR-005 §12.1): republica
 * operações elegíveis cujo job se perdeu e as de lease vencido. A reserva
 * com lease no processador impede processamento duplicado normal.
 */
class DespachanteOutbox
{
    public function __construct(private OutboxIntegracao $outbox) {}

    public function despachar(?int $limite = null): int
    {
        $limite ??= (int) config('integracoes.outbox.lote');

        $operacoes = Operacao::withoutGlobalScope(ImplantacaoScope::class)
            ->where(function ($q) {
                $q->whereIn('estado', EstadoOutbox::elegiveis())->where('disponivel_em', '<=', now());
            })
            ->orWhere(function ($q) {
                $q->where('estado', EstadoOutbox::Processando->value)->where('lease_ate', '<', now());
            })
            ->orderBy('disponivel_em')
            ->limit($limite)
            ->get();

        $operacoes->each(fn (Operacao $operacao) => $this->outbox->publicar($operacao));

        return $operacoes->count();
    }
}

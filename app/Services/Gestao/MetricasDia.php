<?php

namespace App\Services\Gestao;

use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Pessoa;
use App\Models\PreRegistration;
use Illuminate\Support\Carbon;

/**
 * Números de um dia para a gestão (PO 15/11/2026): arrecadação, caixa por
 * operador, entradas e saídas, cadastros e contribuições. Só totais: nenhum
 * nome, documento ou foto de visitante sai do condomínio. O único nome é o
 * do operador responsável pelo caixa.
 */
class MetricasDia
{
    /** @return array<string, mixed> */
    public function calcular(Carbon $dia): array
    {
        $inicio = $dia->copy()->startOfDay();
        $fim = $dia->copy()->endOfDay();

        $contribuicoes = Contribuicao::query()->whereBetween('registrada_em', [$inicio, $fim]);
        $pagas = (clone $contribuicoes)->where('situacao_pagamento', 'pago');
        $lancamentos = CaixaMovimentacao::query()->whereBetween('occurred_at', [$inicio, $fim]);

        $porForma = [];
        foreach ((clone $pagas)->selectRaw('forma_pagamento as forma, sum(valor) as total')->groupBy('forma_pagamento')->get() as $linha) {
            $porForma[$linha->forma ?? 'outro'] = round(($porForma[$linha->forma ?? 'outro'] ?? 0) + (float) $linha->total, 2);
        }
        foreach ((clone $lancamentos)->where('type', 'entrada')->selectRaw('method as forma, sum(amount) as total')->groupBy('method')->get() as $linha) {
            $porForma[$linha->forma ?? 'outro'] = round(($porForma[$linha->forma ?? 'outro'] ?? 0) + (float) $linha->total, 2);
        }

        $valorContribuicoes = round((float) (clone $pagas)->sum('valor'), 2);
        $valorManual = round((float) (clone $lancamentos)->where('type', 'entrada')->sum('amount'), 2);
        $saidas = round((float) (clone $lancamentos)->whereIn('type', ['saida', 'estorno'])->sum('amount'), 2);

        $situacoes = (clone $contribuicoes)->selectRaw('situacao_pagamento as s, count(*) as n')->groupBy('situacao_pagamento')->pluck('n', 's');
        $acessos = HistoricoAcesso::query()->whereBetween('occurred_at', [$inicio, $fim]);

        return [
            'dia' => $inicio->toDateString(),
            'arrecadacao' => [
                'total' => round($valorContribuicoes + $valorManual, 2),
                'contribuicoes' => $valorContribuicoes,
                'lancamentos_manuais' => $valorManual,
                'saidas_e_estornos' => $saidas,
                'por_forma' => $porForma,
            ],
            'contribuicoes' => [
                'pagas' => (int) ($situacoes['pago'] ?? 0),
                'nao_contribui' => (int) ($situacoes['sem_pagamento'] ?? 0),
                'isentas' => (int) ($situacoes['isento'] ?? 0),
            ],
            'acessos' => [
                'entradas' => (clone $acessos)->where('tipo', 'entrada')->where('resultado', 'liberado')->count(),
                'saidas' => (clone $acessos)->where('tipo', 'saida')->count(),
                'negados' => (clone $acessos)->where('resultado', 'negado')->count(),
                'pendentes' => (clone $acessos)->where('resultado', 'pendente')->count(),
                'pessoas' => (clone $acessos)->whereNotNull('pessoa_id')->distinct()->count('pessoa_id'),
            ],
            'cadastros' => [
                'pessoas_novas' => Pessoa::query()->whereBetween('created_at', [$inicio, $fim])->count(),
                'pre_cadastros_recebidos' => PreRegistration::query()->whereBetween('submitted_at', [$inicio, $fim])->count(),
                'pre_cadastros_aguardando' => PreRegistration::query()->where('status', 'aguardando')->count(),
            ],
            'caixas' => $this->caixas($inicio, $fim),
            'gerado_em' => now()->toIso8601String(),
        ];
    }

    /** Caixas abertos no dia ou ainda abertos, com o que cada um arrecadou. @return list<array<string, mixed>> */
    private function caixas(Carbon $inicio, Carbon $fim): array
    {
        return CaixaTurno::query()->with(['operator:id,name', 'posto:id,nome'])
            ->where(fn ($q) => $q->whereBetween('opened_at', [$inicio, $fim])
                ->orWhere(fn ($q) => $q->where('opened_at', '<', $inicio)->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $inicio))))
            ->orderBy('opened_at')->get()
            ->map(fn (CaixaTurno $caixa) => [
                'operador' => $caixa->operator?->name ?? 'Operador removido',
                'posto' => $caixa->posto?->nome ?? $caixa->terminal,
                'aberto_em' => $caixa->opened_at?->toIso8601String(),
                'fechado_em' => $caixa->closed_at?->toIso8601String(),
                'situacao' => $caixa->status,
                'arrecadado' => $caixa->incomeTotal(),
                'saidas' => round($caixa->outflowTotal(), 2),
                'esperado' => $caixa->expectedBalance(),
                'informado' => $caixa->informed_amount !== null ? (float) $caixa->informed_amount : null,
                'diferenca' => $caixa->difference !== null ? (float) $caixa->difference : null,
            ])->values()->all();
    }
}

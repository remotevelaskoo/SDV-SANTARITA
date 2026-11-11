<?php

namespace App\Livewire;

use App\Models\CaixaMovimentacao;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Relatório final do Modo Portaria: fechamento imprimível do período com
 * todas as entradas e saídas, os valores recolhidos com o total de
 * arrecadação e os números de ticket (etiqueta) de cada pessoa que
 * contribuiu.
 *
 * - Reaproveita `historico_acessos`, `contribuicoes` (AJ-006) e
 *   `caixa_movimentacoes`; não cria tabela nem altera o fechamento de caixa.
 * - As entradas e saídas seguem o mesmo escopo dos indicadores do Modo
 *   Portaria (toda a implantação).
 * - Valores, formas de pagamento e lançamentos de caixa só aparecem para quem
 *   opera ou consulta caixa (mesma regra do relatório de acessos, AJ-006).
 *   Sem permissão consolidada, os valores ficam restritos ao próprio operador.
 */
class RelatorioFinalPortaria extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public string $operatorFilter = 'todos';

    public function mount(): void
    {
        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedDateFrom(): void
    {
        $this->normalizarPeriodo();
    }

    public function updatedDateTo(): void
    {
        $this->normalizarPeriodo();
    }

    public function hoje(): void
    {
        $this->mount();
    }

    private function normalizarPeriodo(): void
    {
        $hoje = now()->toDateString();
        $this->dateFrom = $this->dataValida($this->dateFrom) ? $this->dateFrom : $hoje;
        $this->dateTo = $this->dataValida($this->dateTo) ? $this->dateTo : $this->dateFrom;

        if ($this->dateTo < $this->dateFrom) {
            $this->dateTo = $this->dateFrom;
        }
    }

    private function dataValida(string $data): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) === 1 && strtotime($data) !== false;
    }

    private function canViewPayments(): bool
    {
        return Auth::user()?->hasPermission('caixa.proprio.gerenciar')
            || Auth::user()?->hasPermission('caixa.consolidado.consultar');
    }

    private function canViewConsolidated(): bool
    {
        return Auth::user()?->hasPermission('caixa.consolidado.consultar')
            || Auth::user()?->hasPermission('relatorios.consolidado.consultar');
    }

    /** @return Builder<HistoricoAcesso> */
    private function accessQuery(): Builder
    {
        return HistoricoAcesso::query()
            ->with(['pessoa', 'imovel', 'veiculo', 'operator', 'contribuicao'])
            ->whereDate('occurred_at', '>=', $this->dateFrom)
            ->whereDate('occurred_at', '<=', $this->dateTo)
            ->when($this->operatorFilter !== 'todos' && $this->canViewConsolidated(), fn (Builder $q) => $q->where('operator_id', $this->operatorFilter));
    }

    /**
     * Contribuições pagas do período, já no escopo financeiro de quem consulta.
     *
     * @return Collection<int, Contribuicao>
     */
    private function contribuicoesPagas(): Collection
    {
        if (! $this->canViewPayments()) {
            return collect();
        }

        return Contribuicao::query()
            ->with(['historicoAcesso.pessoa', 'historicoAcesso.imovel'])
            ->where('situacao_pagamento', 'pago')
            ->whereDate('registrada_em', '>=', $this->dateFrom)
            ->whereDate('registrada_em', '<=', $this->dateTo)
            ->tap(fn (Builder $q) => $this->aplicarEscopoFinanceiro($q))
            ->orderBy('registrada_em')
            ->get();
    }

    /** @return Collection<int, CaixaMovimentacao> */
    private function movimentacoesCaixa(): Collection
    {
        if (! $this->canViewPayments()) {
            return collect();
        }

        return CaixaMovimentacao::query()
            ->with(['caixaTurno', 'operator'])
            ->whereDate('occurred_at', '>=', $this->dateFrom)
            ->whereDate('occurred_at', '<=', $this->dateTo)
            ->tap(fn (Builder $q) => $this->aplicarEscopoFinanceiro($q))
            ->orderBy('occurred_at')
            ->get();
    }

    /** @param Builder<Contribuicao>|Builder<CaixaMovimentacao> $query */
    private function aplicarEscopoFinanceiro(Builder $query): void
    {
        if (! $this->canViewConsolidated()) {
            $query->where('operator_id', Auth::id());
        } elseif ($this->operatorFilter !== 'todos') {
            $query->where('operator_id', $this->operatorFilter);
        }
    }

    /**
     * Uma linha por pessoa que contribuiu, com todos os tickets dela.
     *
     * @param  Collection<int, Contribuicao>  $contribuicoes
     * @return Collection<int, array{pessoa: string, imovel: string, quantidade: int, tickets: list<string>, formas: string, total: float}>
     */
    private function contribuintes(Collection $contribuicoes): Collection
    {
        return $contribuicoes
            ->groupBy(fn (Contribuicao $c) => $c->pessoa_id ?? 'atendimento:'.$c->historico_acesso_id)
            ->map(function (Collection $grupo): array {
                /** @var Contribuicao $primeira */
                $primeira = $grupo->first();
                $historico = $primeira->historicoAcesso;

                return [
                    'pessoa' => $historico?->pessoa?->nomeExibicao() ?? 'Não identificado',
                    'imovel' => $historico?->imovel?->label() ?? '—',
                    'quantidade' => $grupo->count(),
                    'tickets' => $grupo->pluck('ticket_numero')->filter()->values()->all(),
                    'formas' => $grupo->pluck('forma_pagamento')->filter()->unique()->map(fn ($f) => Contribuicao::rotuloForma($f))->implode(', ') ?: '—',
                    'total' => round((float) $grupo->sum(fn (Contribuicao $c) => (float) $c->valor), 2),
                ];
            })
            ->sortBy('pessoa', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function render(): View
    {
        $this->normalizarPeriodo();

        $acessos = $this->accessQuery()->orderBy('occurred_at')->get();
        $contribuicoes = $this->contribuicoesPagas();
        $movimentacoes = $this->movimentacoesCaixa();

        $totalContribuicoes = round((float) $contribuicoes->sum(fn (Contribuicao $c) => (float) $c->valor), 2);
        $outrasEntradas = round((float) $movimentacoes->where('type', 'entrada')->sum('amount'), 2);
        $estornos = round((float) $movimentacoes->where('type', 'estorno')->sum('amount'), 2);
        $saidas = round((float) $movimentacoes->where('type', 'saida')->sum('amount'), 2);

        $porForma = collect(['dinheiro', 'pix', 'cartao'])
            ->mapWithKeys(fn (string $forma) => [Contribuicao::rotuloForma($forma) => round((float) $contribuicoes->where('forma_pagamento', $forma)->sum(fn (Contribuicao $c) => (float) $c->valor), 2)])
            ->filter(fn (float $valor) => $valor > 0);

        $situacoes = $acessos->map(fn (HistoricoAcesso $a) => $a->contribuicao?->situacao_pagamento);

        return view('livewire.relatorio-final-portaria', [
            'acessos' => $acessos,
            'contribuintes' => $this->contribuintes($contribuicoes),
            'movimentacoes' => $movimentacoes,
            'canViewPayments' => $this->canViewPayments(),
            'canViewConsolidated' => $this->canViewConsolidated(),
            'operators' => $this->canViewConsolidated()
                ? User::query()->whereHas('implantacoes')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'resumo' => [
                'entradas' => $acessos->where('tipo', 'entrada')->count(),
                'saidas' => $acessos->where('tipo', 'saida')->count(),
                'pessoas' => $acessos->pluck('pessoa_id')->filter()->unique()->count(),
                'negados' => $acessos->where('resultado', 'negado')->count(),
                'pagos' => $situacoes->filter(fn ($s) => $s === 'pago')->count(),
                'naoContribui' => $situacoes->filter(fn ($s) => $s === 'sem_pagamento')->count(),
                'isentos' => $situacoes->filter(fn ($s) => $s === 'isento')->count(),
            ],
            'financeiro' => [
                'contribuicoes' => $totalContribuicoes,
                'quantidade' => $contribuicoes->count(),
                'tickets' => $contribuicoes->pluck('ticket_numero')->filter()->count(),
                'porForma' => $porForma,
                'outrasEntradas' => $outrasEntradas,
                'estornos' => $estornos,
                'saidas' => $saidas,
                'totalArrecadado' => round($totalContribuicoes + $outrasEntradas - $estornos, 2),
            ],
            'periodoRotulo' => $this->dateFrom === $this->dateTo
                ? Carbon::parse($this->dateFrom)->format('d/m/Y')
                : Carbon::parse($this->dateFrom)->format('d/m/Y').' a '.Carbon::parse($this->dateTo)->format('d/m/Y'),
        ])->layout('components.layouts.app', [
            'title' => 'Relatório final',
            'heading' => 'Relatório final da portaria',
            'headingDescription' => 'Entradas, saídas, arrecadação e tickets do período',
        ]);
    }
}

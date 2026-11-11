<?php

namespace App\Livewire;

use App\Models\CaixaMovimentacao;
use App\Models\CaixaTurno;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\User;
use App\Services\PerfilAtendimento;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Relatório final do Modo Portaria: fechamento imprimível do turno de caixa
 * (da abertura ao fechamento) ou de um período por datas, com
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
    /** "turno" (padrão: da abertura ao fechamento do caixa) ou "periodo" (por datas). */
    #[Url(as: 'modo')]
    public string $modo = 'turno';

    #[Url(as: 'turno')]
    public string $turnoId = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $operatorFilter = 'todos';

    public function mount(): void
    {
        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->toDateString();

        if ($this->modo !== 'periodo') {
            $this->modo = 'turno';
            $this->turnoId = $this->turnoSelecionado()?->id ?? '';
        }
    }

    public function updatedModo(): void
    {
        $this->modo = $this->modo === 'periodo' ? 'periodo' : 'turno';
        if ($this->modo === 'turno' && $this->turnoId === '') {
            $this->turnoId = $this->turnoSelecionado()?->id ?? '';
        }
    }

    /** @return Builder<CaixaTurno> */
    private function turnosVisiveis(): Builder
    {
        return CaixaTurno::query()
            ->when(! $this->canViewConsolidated(), fn (Builder $q) => $q->where('operator_id', Auth::id()));
    }

    /**
     * Turno pedido (se visível para quem consulta); senão o caixa aberto do
     * próprio operador; senão o último turno que ele fechou.
     */
    private function turnoSelecionado(): ?CaixaTurno
    {
        if ($this->turnoId !== '') {
            $turno = $this->turnosVisiveis()->with('operator')->find($this->turnoId);
            if ($turno) {
                return $turno;
            }
        }

        $proprios = CaixaTurno::query()->with('operator')->where('operator_id', Auth::id());

        return (clone $proprios)->where('status', 'aberto')->latest('opened_at')->first()
            ?? $proprios->where('status', 'fechado')->latest('closed_at')->first();
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
        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->toDateString();
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
    private function accessQuery(?CaixaTurno $turno): Builder
    {
        return HistoricoAcesso::query()
            ->with(['pessoa', 'imovel', 'veiculo', 'operator', 'contribuicao'])
            ->tap(fn (Builder $q) => $this->aplicarJanela($q, 'occurred_at', $turno))
            ->when($turno === null && $this->operatorFilter !== 'todos' && $this->canViewConsolidated(), fn (Builder $q) => $q->where('operator_id', $this->operatorFilter));
    }

    /**
     * Contribuições pagas do período, já no escopo financeiro de quem consulta.
     *
     * @return Collection<int, Contribuicao>
     */
    private function contribuicoesPagas(?CaixaTurno $turno): Collection
    {
        if (! $this->canViewPayments()) {
            return collect();
        }

        return Contribuicao::query()
            ->with(['historicoAcesso.pessoa', 'historicoAcesso.imovel', 'historicoAcesso.veiculo'])
            ->where('situacao_pagamento', 'pago')
            ->tap(fn (Builder $q) => $turno
                ? $q->where('caixa_turno_id', $turno->id)
                : $this->aplicarJanela($q, 'registrada_em', null))
            ->tap(fn (Builder $q) => $turno ? null : $this->aplicarEscopoFinanceiro($q))
            ->orderBy('registrada_em')
            ->get();
    }

    /** @return Collection<int, CaixaMovimentacao> */
    private function movimentacoesCaixa(?CaixaTurno $turno): Collection
    {
        if (! $this->canViewPayments()) {
            return collect();
        }

        return CaixaMovimentacao::query()
            ->with(['caixaTurno', 'operator'])
            ->tap(fn (Builder $q) => $turno
                ? $q->where('caixa_turno_id', $turno->id)
                : $this->aplicarJanela($q, 'occurred_at', null))
            ->tap(fn (Builder $q) => $turno ? null : $this->aplicarEscopoFinanceiro($q))
            ->orderBy('occurred_at')
            ->get();
    }

    /**
     * No turno: da abertura ao fechamento (ou até agora, se ainda aberto).
     * No período: dias inteiros entre as datas escolhidas.
     *
     * @param  Builder<HistoricoAcesso>|Builder<Contribuicao>|Builder<CaixaMovimentacao>  $query
     */
    private function aplicarJanela(Builder $query, string $coluna, ?CaixaTurno $turno): void
    {
        if ($turno) {
            $query->where($coluna, '>=', $turno->opened_at)->where($coluna, '<=', $turno->closed_at ?? now());

            return;
        }

        $query->whereDate($coluna, '>=', $this->dateFrom)->whereDate($coluna, '<=', $this->dateTo);
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
    private function contribuintes(Collection $contribuicoes, array $perfis): Collection
    {
        return $contribuicoes
            ->groupBy(fn (Contribuicao $c) => $c->pessoa_id ?? 'atendimento:'.$c->historico_acesso_id)
            ->map(function (Collection $grupo) use ($perfis): array {
                /** @var Contribuicao $primeira */
                $primeira = $grupo->first();
                $historico = $primeira->historicoAcesso;
                $perfil = $perfis[$primeira->historico_acesso_id] ?? ['perfil' => 'Sem vínculo', 'local' => '—'];

                return [
                    'pessoa' => $historico?->pessoa?->nomeExibicao() ?? 'Não identificado',
                    'perfil' => $perfil['perfil'],
                    'imovel' => $perfil['local'],
                    'placas' => $grupo->map(fn (Contribuicao $c) => $c->historicoAcesso ? self::placa($c->historicoAcesso) : null)->filter()->unique()->values()->all(),
                    'quantidade' => $grupo->count(),
                    'tickets' => $grupo->pluck('ticket_numero')->filter()->values()->all(),
                    'formas' => $grupo->pluck('forma_pagamento')->filter()->unique()->map(fn ($f) => Contribuicao::rotuloForma($f))->implode(', ') ?: '—',
                    'total' => round((float) $grupo->sum(fn (Contribuicao $c) => (float) $c->valor), 2),
                ];
            })
            ->sortBy('pessoa', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** Placa do veículo cadastrado ou, sem cadastro, a placa informada/lida no atendimento. */
    public static function placa(HistoricoAcesso $acesso): ?string
    {
        return $acesso->veiculo?->plate_display ?: ($acesso->placa_informada ?: null);
    }

    /**
     * Perfil de cada atendimento e o local (turista = Praia), via PerfilAtendimento.
     *
     * @param  Collection<int, HistoricoAcesso>  $acessos
     * @return array<string, array{perfil: string, local: string, turista: bool}>
     */
    private function perfis(Collection $acessos): array
    {
        return app(PerfilAtendimento::class)->resolver($acessos->map(fn (HistoricoAcesso $a) => [
            'chave' => $a->id,
            'pessoa_id' => $a->pessoa_id,
            'momento' => $a->occurred_at,
            'imovel_id' => $a->imovel_id,
            'imovel' => $a->imovel?->label(),
        ]));
    }

    private function periodoRotulo(?CaixaTurno $turno, bool $semTurno): string
    {
        if ($turno) {
            $fim = $turno->closed_at?->format('d/m/Y H:i') ?? 'agora (caixa aberto)';

            return "{$turno->terminal} · ".($turno->operator?->name ?? 'Sistema')." · {$turno->opened_at->format('d/m/Y H:i')} até {$fim}";
        }

        if ($semTurno) {
            return 'Nenhum turno de caixa';
        }

        $inicio = Carbon::parse($this->dateFrom)->format('d/m/Y');

        return $this->dateFrom === $this->dateTo ? $inicio : $inicio.' a '.Carbon::parse($this->dateTo)->format('d/m/Y');
    }

    public function render(): View
    {
        $this->normalizarPeriodo();

        $turno = $this->modo === 'turno' ? $this->turnoSelecionado() : null;
        $semTurno = $this->modo === 'turno' && $turno === null;

        $acessos = $semTurno ? collect() : $this->accessQuery($turno)->orderBy('occurred_at')->get();
        $contribuicoes = $semTurno ? collect() : $this->contribuicoesPagas($turno);
        $movimentacoes = $semTurno ? collect() : $this->movimentacoesCaixa($turno);

        $perfis = $this->perfis($acessos->merge($contribuicoes->pluck('historicoAcesso')->filter())->unique('id'));

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
            'perfis' => $perfis,
            'porPerfil' => $acessos->where('tipo', 'entrada')
                ->groupBy(fn (HistoricoAcesso $a) => $perfis[$a->id]['perfil'] ?? 'Sem vínculo')
                ->map(fn (Collection $grupo) => $grupo->count())
                ->sortDesc(),
            'turno' => $turno,
            'semTurno' => $semTurno,
            'turnos' => $this->turnosVisiveis()->with('operator')->latest('opened_at')->limit(30)->get(),
            'caixa' => $turno ? [
                'saldoInicial' => (float) $turno->opening_balance,
                'esperado' => $turno->status === 'fechado' ? (float) $turno->expected_amount : $turno->expectedBalance(),
                'informado' => $turno->status === 'fechado' ? (float) $turno->informed_amount : null,
                'diferenca' => $turno->status === 'fechado' ? (float) $turno->difference : null,
            ] : null,
            'contribuintes' => $this->contribuintes($contribuicoes, $perfis),
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
            'periodoRotulo' => $this->periodoRotulo($turno, $semTurno),
        ])->layout('components.layouts.app', [
            'title' => 'Relatório final',
            'heading' => 'Relatório final da portaria',
            'headingDescription' => 'Entradas, saídas, arrecadação e tickets do turno de caixa',
        ]);
    }
}

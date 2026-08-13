<?php

namespace App\Livewire;

use App\Models\CaixaMovimentacao;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Component
{
    public string $reportType = 'acessos';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $search = '';

    public string $operatorFilter = 'todos';

    public string $resultFilter = 'todos';

    public string $accessTypeFilter = 'todos';

    public string $pointFilter = 'todos';

    public string $propertyFilter = 'todos';

    public string $movementTypeFilter = 'todos';

    public string $paymentMethodFilter = 'todos';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedReportType(string $type): void
    {
        if (! in_array($type, ['acessos', 'caixa'], true)) {
            $this->reportType = 'acessos';
        }
    }

    public function clearFilters(): void
    {
        $type = $this->reportType;
        $this->reset();
        $this->reportType = $type;
        $this->mount();
    }

    public function exportCsv(AuditService $audit): StreamedResponse
    {
        abort_unless($this->canViewReports(), 403);

        $type = $this->reportType === 'caixa' ? 'caixa' : 'acessos';
        $filename = "relatorio-{$type}-{$this->dateFrom}-a-{$this->dateTo}.csv";

        $audit->record(
            action: 'exportou_csv',
            module: 'relatorios',
            entityType: 'relatorio_'.$type,
            classification: 'restrita',
            metadata: ['date_from' => $this->dateFrom, 'date_to' => $this->dateTo],
        );

        return response()->streamDownload(function () use ($type): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            if ($type === 'acessos') {
                fputcsv($output, ['Data e hora', 'Pessoa', 'Imóvel', 'Ponto', 'Tipo', 'Resultado', 'Placa', 'Operador', 'Protocolo'], ';');
                $this->accessQuery()->orderBy('occurred_at')->each(function (HistoricoAcesso $entry) use ($output): void {
                    fputcsv($output, [
                        $entry->occurred_at->format('d/m/Y H:i'),
                        $entry->pessoa?->nomeExibicao() ?? 'Não identificado',
                        $entry->imovel?->label() ?? '',
                        $entry->ponto_acesso,
                        $entry->tipo,
                        $entry->resultado,
                        $entry->veiculo?->plate_display ?? '',
                        $entry->operator?->name ?? 'Sistema',
                        $entry->protocol,
                    ], ';');
                });
            } else {
                fputcsv($output, ['Data e hora', 'Caixa', 'Tipo', 'Forma', 'Descrição', 'Valor', 'Operador', 'Protocolo'], ';');
                $this->cashQuery()->orderBy('occurred_at')->each(function (CaixaMovimentacao $movement) use ($output): void {
                    fputcsv($output, [
                        $movement->occurred_at->format('d/m/Y H:i'),
                        $movement->caixaTurno?->terminal ?? '',
                        $movement->type,
                        $movement->method,
                        $movement->description,
                        number_format((float) $movement->amount, 2, ',', ''),
                        $movement->operator?->name ?? 'Sistema',
                        $movement->protocol ?? '',
                    ], ';');
                });
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Builder<HistoricoAcesso> */
    private function accessQuery(): Builder
    {
        $query = HistoricoAcesso::query()->with(['pessoa', 'imovel', 'veiculo', 'operator'])
            ->whereDate('occurred_at', '>=', $this->dateFrom)
            ->whereDate('occurred_at', '<=', $this->dateTo);

        $this->applyOperatorScope($query);

        return $query
            ->when($this->resultFilter !== 'todos', fn (Builder $query) => $query->where('resultado', $this->resultFilter))
            ->when($this->accessTypeFilter !== 'todos', fn (Builder $query) => $query->where('tipo', $this->accessTypeFilter))
            ->when($this->pointFilter !== 'todos', fn (Builder $query) => $query->where('ponto_acesso', $this->pointFilter))
            ->when($this->propertyFilter !== 'todos', fn (Builder $query) => $query->where('imovel_id', $this->propertyFilter))
            ->when($this->operatorFilter !== 'todos' && $this->canViewConsolidated(), fn (Builder $query) => $query->where('operator_id', $this->operatorFilter))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('protocol', 'like', $search)
                        ->orWhereHas('pessoa', fn (Builder $query) => $query->where('nome', 'like', $search)->orWhere('nome_social', 'like', $search))
                        ->orWhereHas('veiculo', fn (Builder $query) => $query->where('plate_normalized', 'like', strtoupper(str_replace(['-', ' '], '', $search))));
                });
            });
    }

    /** @return Builder<CaixaMovimentacao> */
    private function cashQuery(): Builder
    {
        $query = CaixaMovimentacao::query()->with(['caixaTurno', 'operator'])
            ->whereDate('occurred_at', '>=', $this->dateFrom)
            ->whereDate('occurred_at', '<=', $this->dateTo);

        $this->applyOperatorScope($query);

        return $query
            ->when($this->movementTypeFilter !== 'todos', fn (Builder $query) => $query->where('type', $this->movementTypeFilter))
            ->when($this->paymentMethodFilter !== 'todos', fn (Builder $query) => $query->where('method', $this->paymentMethodFilter))
            ->when($this->operatorFilter !== 'todos' && $this->canViewConsolidated(), fn (Builder $query) => $query->where('operator_id', $this->operatorFilter))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn (Builder $query) => $query->where('description', 'like', $search)->orWhere('protocol', 'like', $search));
            });
    }

    /** @param Builder<HistoricoAcesso>|Builder<CaixaMovimentacao> $query */
    private function applyOperatorScope(Builder $query): void
    {
        if (! $this->canViewConsolidated()) {
            $query->where('operator_id', Auth::id());
        }
    }

    /** @return Collection<int, HistoricoAcesso> */
    private function accessRows(): Collection
    {
        return $this->accessQuery()->orderByDesc('occurred_at')->limit(500)->get();
    }

    /** @return Collection<int, CaixaMovimentacao> */
    private function cashRows(): Collection
    {
        return $this->cashQuery()->orderByDesc('occurred_at')->limit(500)->get();
    }

    private function canViewReports(): bool
    {
        return Auth::user()?->hasPermission('relatorios.proprio.consultar')
            || Auth::user()?->hasPermission('relatorios.consolidado.consultar');
    }

    private function canViewConsolidated(): bool
    {
        return Auth::user()?->hasPermission('relatorios.consolidado.consultar') ?? false;
    }

    public function render(): View
    {
        abort_unless($this->canViewReports(), 403);

        $accessRows = $this->reportType === 'acessos' ? $this->accessRows() : collect();
        $cashRows = $this->reportType === 'caixa' ? $this->cashRows() : collect();

        return view('livewire.reports', [
            'accessRows' => $accessRows,
            'cashRows' => $cashRows,
            'canViewConsolidated' => $this->canViewConsolidated(),
            'operators' => $this->canViewConsolidated()
                ? User::query()->whereHas('implantacoes')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'points' => HistoricoAcesso::query()->distinct()->orderBy('ponto_acesso')->pluck('ponto_acesso'),
            'properties' => Imovel::query()->orderBy('codigo')->get(),
            'accessSummary' => [
                'total' => $accessRows->count(),
                'liberado' => $accessRows->where('resultado', 'liberado')->count(),
                'negado' => $accessRows->where('resultado', 'negado')->count(),
                'pendente' => $accessRows->where('resultado', 'pendente')->count(),
            ],
            'cashSummary' => [
                'total' => $cashRows->sum(fn (CaixaMovimentacao $movement) => $movement->type === 'entrada' ? (float) $movement->amount : -(float) $movement->amount),
                'entradas' => $cashRows->where('type', 'entrada')->sum('amount'),
                'saidas' => $cashRows->whereIn('type', ['saida', 'estorno'])->sum('amount'),
                'movimentos' => $cashRows->count(),
            ],
        ])->layout('components.layouts.app', [
            'title' => 'Relatórios',
            'heading' => 'Relatórios',
            'headingDescription' => $this->canViewConsolidated() ? 'Visão consolidada da implantação' : 'Visão limitada às suas próprias operações',
        ]);
    }
}

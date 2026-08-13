<?php

namespace App\Livewire;

use App\Models\AuditoriaEvento;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLog extends Component
{
    use WithPagination;

    public string $search = '';

    public string $moduleFilter = 'todos';

    public string $actionFilter = 'todos';

    public string $resultFilter = 'todos';

    public string $actorFilter = 'todos';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?string $selectedEventId = null;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updated(string $property): void
    {
        if ($property !== 'selectedEventId') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'moduleFilter', 'actionFilter', 'resultFilter', 'actorFilter']);
        $this->mount();
        $this->resetPage();
    }

    public function openEvent(string $id, AuditService $audit): void
    {
        $event = AuditoriaEvento::query()->find($id);
        if (! $event) {
            return;
        }

        $this->selectedEventId = $id;

        $audit->record(
            action: 'consultou_detalhe',
            module: 'auditoria',
            entityType: 'auditoria_eventos',
            entityId: $id,
            metadata: ['consulted_event_id' => $id],
        );
    }

    public function closeEvent(): void
    {
        $this->selectedEventId = null;
    }

    public function exportCsv(AuditService $audit): StreamedResponse
    {
        abort_unless($this->canExport(), 403);

        $audit->record(
            action: 'exportou_csv',
            module: 'auditoria',
            entityType: 'auditoria_eventos',
            metadata: ['date_from' => $this->dateFrom, 'date_to' => $this->dateTo],
        );

        $filename = "auditoria-{$this->dateFrom}-a-{$this->dateTo}.csv";

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Data e hora', 'Usuário', 'Perfil', 'Módulo', 'Operação', 'Entidade', 'Identificador', 'Origem', 'Resultado', 'Correlação'], ';');

            $this->filteredQuery()->orderBy('occurred_at')->orderBy('id')->each(function (AuditoriaEvento $event) use ($output): void {
                fputcsv($output, [
                    $event->occurred_at->format('d/m/Y H:i:s'),
                    $event->actor_name,
                    $event->actor_profile,
                    $event->module,
                    $event->action,
                    $event->entity_type,
                    $event->entity_id,
                    $event->origin,
                    $event->result,
                    $event->correlation_id,
                ], ';');
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Builder<AuditoriaEvento> */
    private function filteredQuery(): Builder
    {
        return AuditoriaEvento::query()
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('occurred_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('occurred_at', '<=', $this->dateTo))
            ->when($this->moduleFilter !== 'todos', fn (Builder $query) => $query->where('module', $this->moduleFilter))
            ->when($this->actionFilter !== 'todos', fn (Builder $query) => $query->where('action', $this->actionFilter))
            ->when($this->resultFilter !== 'todos', fn (Builder $query) => $query->where('result', $this->resultFilter))
            ->when($this->actorFilter !== 'todos', fn (Builder $query) => $query->where('actor_id', $this->actorFilter))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('actor_name', 'like', $search)
                        ->orWhere('entity_type', 'like', $search)
                        ->orWhere('entity_id', 'like', $search)
                        ->orWhere('correlation_id', 'like', $search);
                });
            });
    }

    /** @return LengthAwarePaginator<int, AuditoriaEvento> */
    private function events(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20);
    }

    private function selectedEvent(): ?AuditoriaEvento
    {
        return $this->selectedEventId
            ? AuditoriaEvento::query()->with(['changes', 'context'])->find($this->selectedEventId)
            : null;
    }

    private function canExport(): bool
    {
        return Auth::user()?->hasPermission('auditoria.exportar') ?? false;
    }

    public function render(): View
    {
        abort_unless(Auth::user()?->hasPermission('auditoria.consultar'), 403);

        return view('livewire.audit-log', [
            'events' => $this->events(),
            'selectedEvent' => $this->selectedEvent(),
            'modules' => AuditoriaEvento::query()->distinct()->orderBy('module')->pluck('module'),
            'actions' => AuditoriaEvento::query()->distinct()->orderBy('action')->pluck('action'),
            'actors' => User::query()->whereIn('id', AuditoriaEvento::query()->whereNotNull('actor_id')->select('actor_id'))->orderBy('name')->get(['id', 'name']),
            'canExport' => $this->canExport(),
        ])->layout('components.layouts.app', [
            'title' => 'Logs e auditoria',
            'heading' => 'Logs e auditoria',
            'headingDescription' => 'Histórico protegido e imutável das operações do sistema',
        ]);
    }
}

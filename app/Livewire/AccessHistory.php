<?php

namespace App\Livewire;

use App\Models\HistoricoAcesso;
use App\Models\Vinculo;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AccessHistory extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $typeFilter = 'todos';

    public string $resultFilter = 'todos';

    public string $pointFilter = 'todos';

    public ?string $selectedEntryId = null;

    public function setTypeFilter(string $type): void
    {
        if (in_array($type, ['todos', 'entrada', 'saida'], true)) {
            $this->typeFilter = $type;
        }
    }

    public function setResultFilter(string $result): void
    {
        if (in_array($result, ['todos', 'liberado', 'negado', 'pendente'], true)) {
            $this->resultFilter = $result;
        }
    }

    public function openEntry(string $id): void
    {
        if (HistoricoAcesso::query()->whereKey($id)->exists()) {
            $this->selectedEntryId = $id;
            $this->mode = 'detail';
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->selectedEntryId = null;
    }

    /** @return list<array<string, mixed>> */
    public function filteredEntries(): array
    {
        $search = mb_strtolower(trim($this->search));

        $query = HistoricoAcesso::query()->with(['pessoa.documentos', 'imovel', 'veiculo', 'operator']);

        if ($this->typeFilter !== 'todos') {
            $query->where('tipo', $this->typeFilter);
        }

        if ($this->resultFilter !== 'todos') {
            $query->where('resultado', $this->resultFilter);
        }

        if ($this->pointFilter !== 'todos') {
            $query->where('ponto_acesso', $this->pointFilter);
        }

        $entries = $query->orderByDesc('occurred_at')->get();

        if ($search !== '') {
            $entries = $entries->filter(fn (HistoricoAcesso $entry) => str_contains(
                mb_strtolower(implode(' ', array_filter([
                    $entry->pessoa?->nomeExibicao(),
                    $entry->pessoa?->documentos->first()?->valor_apresentacao,
                    $entry->imovel?->codigo,
                    $entry->veiculo?->plate_display,
                    $entry->protocol,
                ]))),
                $search
            ));
        }

        return $entries->map(fn (HistoricoAcesso $entry) => $this->toArray($entry))->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function selectedEntry(): ?array
    {
        if ($this->selectedEntryId === null) {
            return null;
        }

        $entry = HistoricoAcesso::query()->with(['pessoa.documentos', 'imovel', 'veiculo', 'operator'])->find($this->selectedEntryId);

        return $entry ? $this->toArray($entry) : null;
    }

    /** @return list<string> */
    public function points(): array
    {
        return HistoricoAcesso::query()->distinct()->orderBy('ponto_acesso')->pluck('ponto_acesso')->all();
    }

    /** @return array{total: int, liberado: int, negado: int, pendente: int} */
    public function entryCounts(): array
    {
        return [
            'total' => HistoricoAcesso::query()->count(),
            'liberado' => HistoricoAcesso::query()->where('resultado', 'liberado')->count(),
            'negado' => HistoricoAcesso::query()->where('resultado', 'negado')->count(),
            'pendente' => HistoricoAcesso::query()->where('resultado', 'pendente')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function toArray(HistoricoAcesso $entry): array
    {
        $vinculo = $entry->pessoa
            ? Vinculo::query()->where('pessoa_id', $entry->pessoa->id)->whereNull('ended_at')->first()
            : null;

        return [
            'id' => $entry->id,
            'datetime' => $entry->occurred_at->format('d/m/Y H:i'),
            'name' => $entry->pessoa?->nomeExibicao() ?? 'Não identificado',
            'document' => $entry->pessoa?->documentos->first()?->valor_apresentacao ?? '—',
            'relation' => $this->relationLabel($vinculo?->tipo),
            'property' => $entry->imovel?->label() ?? '—',
            'point' => $entry->ponto_acesso,
            'type' => $entry->tipo,
            'result' => $entry->resultado,
            'plate' => $entry->veiculo?->plate_display,
            'operator' => $entry->operator?->name ?? 'Sistema',
            'reason' => $entry->motivo_negacao,
            'notes' => $entry->notes,
            'protocol' => $entry->protocol,
        ];
    }

    private function relationLabel(?string $tipo): string
    {
        return match ($tipo) {
            'proprietario' => 'Proprietário',
            'inquilino' => 'Inquilino',
            'morador' => 'Morador',
            'prestador' => 'Prestador',
            'visitante' => 'Visitante',
            default => 'Visitante',
        };
    }

    public function render(): View
    {
        return view('livewire.access-history', [
            'filteredEntries' => $this->filteredEntries(),
            'selectedEntry' => $this->selectedEntry(),
            'points' => $this->points(),
            'entryCounts' => $this->entryCounts(),
        ])->layout('components.layouts.app', [
            'title' => 'Entradas e saídas',
            'heading' => $this->mode === 'detail' ? 'Detalhe do registro' : 'Entradas e saídas',
            'headingDescription' => 'Histórico de tentativas de acesso — registros não podem ser editados (RN-048)',
        ]);
    }
}

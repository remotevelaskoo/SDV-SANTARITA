<?php

namespace App\Livewire;

use App\Models\PreRegistration;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * Fila de pré-cadastros. A análise de cada solicitação acontece na página
 * própria (PreRegistrationReview) desde o AJ-006.
 */
class PreRegistrationQueue extends Component
{
    public string $statusFilter = 'aguardando';

    public string $search = '';

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['aguardando', 'aprovado', 'correcao', 'rejeitado', 'todos'], true)) {
            $this->statusFilter = $status;
        }
    }

    /** @return Builder<PreRegistration> */
    private function baseQuery(): Builder
    {
        return PreRegistration::query()
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('protocol', 'like', $term)
                        ->orWhere('vehicle_plate', 'like', $term)
                        ->orWhere('destination_label', 'like', $term);
                });
            })
            ->orderBy('submitted_at');
    }

    /** @return Collection<int, PreRegistration> */
    public function allRecords(): Collection
    {
        return $this->baseQuery()->get();
    }

    /** @return Collection<int, PreRegistration> */
    public function filteredRecords(): Collection
    {
        return $this->baseQuery()
            ->when($this->statusFilter !== 'todos', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->get();
    }

    public function render(): View
    {
        return view('livewire.pre-registration-queue', [
            'records' => $this->allRecords(),
            'filteredRecords' => $this->filteredRecords(),
        ])->layout('components.layouts.app', [
            'title' => 'Pré-cadastros',
            'heading' => 'Pré-cadastros',
            'headingDescription' => 'Solicitações antecipadas aguardando análise da portaria',
        ]);
    }
}

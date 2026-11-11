<?php

namespace App\Livewire;

use App\Models\PreRegistration;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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
            ->with('fileLinks.file')
            ->when($this->statusFilter !== 'todos', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->get();
    }

    /**
     * Foto de cada linha: a selfie vigente (do solicitante ou tirada pela
     * portaria). Sem a permissão de imagens sensíveis, nenhuma URL é gerada.
     *
     * @param  Collection<int, PreRegistration>  $records
     * @return array<string, array{tem: bool, url: ?string}>
     */
    private function fotos(Collection $records): array
    {
        $podeVer = (bool) Auth::user()?->hasPermission('arquivos.sensiveis.visualizar');
        $fotos = [];

        foreach ($records as $record) {
            $arquivo = $record->currentFileLink('selfie')?->file;
            $fotos[$record->id] = [
                'tem' => $arquivo !== null,
                'url' => $podeVer && $arquivo?->isAvailable()
                    ? route('protected-files.show', ['arquivo' => $arquivo, 'contexto' => 'fila_pre_cadastro'])
                    : null,
            ];
        }

        return $fotos;
    }

    public function render(): View
    {
        $filteredRecords = $this->filteredRecords();

        return view('livewire.pre-registration-queue', [
            'records' => $this->allRecords(),
            'filteredRecords' => $filteredRecords,
            'fotos' => $this->fotos($filteredRecords),
            'podeTirarFoto' => (bool) Auth::user()?->hasPermission('pre-registro.editar'),
        ])->layout('components.layouts.app', [
            'title' => 'Pré-cadastros',
            'heading' => 'Pré-cadastros',
            'headingDescription' => 'Solicitações antecipadas aguardando análise da portaria',
        ]);
    }
}

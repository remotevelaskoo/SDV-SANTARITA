<?php

namespace App\Livewire;

use App\Models\Encomenda;
use App\Models\Imovel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PackageManagement extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todas';

    public ?string $selectedPackageId = null;

    // Formulário de recebimento
    public string $recipientName = '';

    public string $property = '';

    public string $carrier = '';

    public string $type = 'caixa';

    public string $storageLocation = '';

    public string $notes = '';

    // Ações de aviso e entrega
    public string $deliveredTo = '';

    public string $deliveryNotes = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['todas', 'aguardando', 'avisado', 'entregue'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function openPackage(string $id): void
    {
        if (Encomenda::query()->whereKey($id)->exists()) {
            $this->selectedPackageId = $id;
            $this->mode = 'detail';
            $this->feedback = null;
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->selectedPackageId = null;
    }

    public function createPackage(): void
    {
        $this->resetForm();
        $this->mode = 'form';
    }

    public function savePackage(): void
    {
        $this->validate([
            'recipientName' => ['required', 'string', 'max:160'],
            'property' => ['required', 'string'],
            'carrier' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['caixa', 'envelope', 'volume', 'outro'])],
            'storageLocation' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:200'],
        ], [
            'required' => 'Preencha este campo para registrar a encomenda.',
        ]);

        $imovel = Imovel::query()->where('codigo', $this->property)->first();

        if (! $imovel) {
            $this->addError('property', 'Selecione um imóvel válido.');

            return;
        }

        $encomenda = Encomenda::query()->create([
            'protocol' => $this->generateProtocol(),
            'recipient_name' => $this->recipientName,
            'imovel_id' => $imovel->id,
            'carrier' => $this->carrier,
            'type' => $this->type,
            'storage_location' => $this->storageLocation,
            'status' => 'aguardando',
            'received_at' => now(),
            'received_by' => Auth::id(),
            'notes' => $this->notes !== '' ? $this->notes : null,
        ]);

        $this->selectedPackageId = $encomenda->id;
        $this->mode = 'detail';
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Encomenda registrada',
            'message' => 'Recebimento confirmado. O morador ainda não foi avisado.',
        ];
    }

    public function notifyRecipient(): void
    {
        $encomenda = Encomenda::query()->find($this->selectedPackageId);

        if (! $encomenda || $encomenda->status !== 'aguardando') {
            return;
        }

        $encomenda->update(['status' => 'avisado', 'notified_at' => now()]);

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Morador avisado',
            'message' => 'Notificação registrada. A encomenda continua armazenada até a retirada.',
        ];
    }

    public function deliverPackage(): void
    {
        $encomenda = Encomenda::query()->find($this->selectedPackageId);

        if (! $encomenda || $encomenda->status === 'entregue') {
            return;
        }

        $this->validate([
            'deliveredTo' => ['required', 'string', 'max:160'],
            'deliveryNotes' => ['nullable', 'string', 'max:200'],
        ], [
            'required' => 'Informe quem retirou a encomenda.',
        ]);

        $encomenda->update([
            'status' => 'entregue',
            'delivered_at' => now(),
            'delivered_to' => $this->deliveredTo,
            'notes' => $this->deliveryNotes !== '' ? trim(($encomenda->notes ?? '').' '.$this->deliveryNotes) : $encomenda->notes,
        ]);

        $this->reset(['deliveredTo', 'deliveryNotes']);
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Entrega registrada',
            'message' => 'A encomenda foi marcada como entregue. O histórico permanece preservado.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function filteredPackages(): array
    {
        $search = mb_strtolower(trim($this->search));

        $query = Encomenda::query()->with(['imovel', 'recebidaPor']);

        if ($this->statusFilter !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        $encomendas = $query->orderByDesc('received_at')->get();

        if ($search !== '') {
            $encomendas = $encomendas->filter(fn (Encomenda $encomenda) => str_contains(
                mb_strtolower(implode(' ', array_filter([
                    $encomenda->recipient_name, $encomenda->imovel->codigo, $encomenda->protocol, $encomenda->carrier,
                ]))),
                $search
            ));
        }

        return $encomendas->map(fn (Encomenda $encomenda) => $this->toArray($encomenda))->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function selectedPackage(): ?array
    {
        if ($this->selectedPackageId === null) {
            return null;
        }

        $encomenda = Encomenda::query()->with(['imovel', 'recebidaPor'])->find($this->selectedPackageId);

        return $encomenda ? $this->toArray($encomenda) : null;
    }

    /** @return array{aguardando: int, avisado: int, entregue: int} */
    public function packageCounts(): array
    {
        return [
            'aguardando' => Encomenda::query()->where('status', 'aguardando')->count(),
            'avisado' => Encomenda::query()->where('status', 'avisado')->count(),
            'entregue' => Encomenda::query()->where('status', 'entregue')->count(),
        ];
    }

    /** @return Collection<int, Imovel> */
    public function imoveis(): Collection
    {
        return Imovel::query()->orderBy('codigo')->get();
    }

    /** @return array<string, mixed> */
    private function toArray(Encomenda $encomenda): array
    {
        return [
            'id' => $encomenda->id,
            'protocol' => $encomenda->protocol,
            'recipient' => $encomenda->recipient_name,
            'property' => $encomenda->imovel->label(),
            'carrier' => $encomenda->carrier,
            'type' => $encomenda->type,
            'storageLocation' => $encomenda->storage_location,
            'status' => $encomenda->status,
            'receivedAt' => $encomenda->received_at->format('d/m/Y H:i'),
            'receivedBy' => $encomenda->recebidaPor?->name ?? 'Sistema',
            'notifiedAt' => $encomenda->notified_at?->format('d/m/Y H:i'),
            'deliveredAt' => $encomenda->delivered_at?->format('d/m/Y H:i'),
            'deliveredTo' => $encomenda->delivered_to,
            'notes' => $encomenda->notes,
        ];
    }

    private function generateProtocol(): string
    {
        return 'SRE-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function resetForm(): void
    {
        $this->recipientName = '';
        $this->property = '';
        $this->carrier = '';
        $this->type = 'caixa';
        $this->storageLocation = '';
        $this->notes = '';
        $this->feedback = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.package-management', [
            'filteredPackages' => $this->filteredPackages(),
            'selectedPackage' => $this->selectedPackage(),
            'packageCounts' => $this->packageCounts(),
            'totalPackages' => Encomenda::query()->count(),
            'imoveis' => $this->imoveis(),
        ])->layout('components.layouts.app', [
            'title' => 'Encomendas',
            'heading' => $this->mode === 'form' ? 'Registrar encomenda' : ($this->mode === 'detail' ? 'Detalhe da encomenda' : 'Encomendas'),
            'headingDescription' => 'Recebimento, armazenamento, aviso e entrega de pacotes',
        ]);
    }
}

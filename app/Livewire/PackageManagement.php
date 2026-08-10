<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PackageManagement extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todas';

    public ?int $selectedPackageId = null;

    // Formulário de recebimento
    public string $recipientName = '';

    public string $property = 'Bloco B — Apto 304';

    public string $carrier = '';

    public string $type = 'caixa';

    public string $storageLocation = '';

    public string $notes = '';

    // Ações de aviso e entrega
    public string $deliveredTo = '';

    public string $deliveryNotes = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    /** @var list<array<string, mixed>> */
    public array $packages = [
        [
            'id' => 1, 'protocol' => 'SRE-20260810-0041', 'recipient' => 'Marcos Vinicius da Silva', 'property' => 'Bloco A — Apto 102', 'carrier' => 'Correios', 'type' => 'caixa', 'storageLocation' => 'Prateleira A3', 'status' => 'entregue', 'receivedAt' => '10/08/2026 09:12', 'receivedBy' => 'Tatiane Souza', 'notifiedAt' => '10/08/2026 09:14', 'deliveredAt' => '10/08/2026 18:30', 'deliveredTo' => 'Marcos Vinicius da Silva', 'notes' => null,
        ],
        [
            'id' => 2, 'protocol' => 'SRE-20260810-0042', 'recipient' => 'Bianca Moretti', 'property' => 'Bloco A — Apto 208', 'carrier' => 'Mercado Livre', 'type' => 'caixa', 'storageLocation' => 'Prateleira B1', 'status' => 'avisado', 'receivedAt' => '10/08/2026 11:05', 'receivedBy' => 'Tatiane Souza', 'notifiedAt' => '10/08/2026 11:07', 'deliveredAt' => null, 'deliveredTo' => null, 'notes' => 'Volume grande, não empilhar.',
        ],
        [
            'id' => 3, 'protocol' => 'SRE-20260810-0043', 'recipient' => 'Rafael Domingues', 'property' => 'Bloco C — Apto 501', 'carrier' => 'Amazon', 'type' => 'envelope', 'storageLocation' => 'Gaveta C2', 'status' => 'aguardando', 'receivedAt' => '10/08/2026 14:40', 'receivedBy' => 'Tatiane Souza', 'notifiedAt' => null, 'deliveredAt' => null, 'deliveredTo' => null, 'notes' => null,
        ],
        [
            'id' => 4, 'protocol' => 'SRE-20260810-0044', 'recipient' => 'Mariana Souza', 'property' => 'Bloco B — Apto 304', 'carrier' => 'Transportadora Vale Express', 'type' => 'volume', 'storageLocation' => 'Depósito — Setor 2', 'status' => 'aguardando', 'receivedAt' => '10/08/2026 16:20', 'receivedBy' => 'Tatiane Souza', 'notifiedAt' => null, 'deliveredAt' => null, 'deliveredTo' => null, 'notes' => 'Requer duas pessoas para transporte.',
        ],
        [
            'id' => 5, 'protocol' => 'SRE-20260809-0038', 'recipient' => 'Eduardo Nogueira', 'property' => 'Bloco A — Apto 112', 'carrier' => 'Correios', 'type' => 'envelope', 'storageLocation' => 'Gaveta C1', 'status' => 'entregue', 'receivedAt' => '09/08/2026 10:02', 'receivedBy' => 'Marcos Almeida', 'notifiedAt' => '09/08/2026 10:05', 'deliveredAt' => '09/08/2026 19:40', 'deliveredTo' => 'Eduardo Nogueira', 'notes' => null,
        ],
    ];

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['todas', 'aguardando', 'avisado', 'entregue'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function openPackage(int $id): void
    {
        if ($this->findPackage($id)) {
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

        $id = count($this->packages) ? max(array_column($this->packages, 'id')) + 1 : 1;

        $this->packages[] = [
            'id' => $id,
            'protocol' => 'SRE-'.now()->format('Ymd').'-'.str_pad((string) (count($this->packages) + 40), 4, '0', STR_PAD_LEFT),
            'recipient' => $this->recipientName,
            'property' => $this->property,
            'carrier' => $this->carrier,
            'type' => $this->type,
            'storageLocation' => $this->storageLocation,
            'status' => 'aguardando',
            'receivedAt' => now()->format('d/m/Y H:i'),
            'receivedBy' => 'Tatiane Souza',
            'notifiedAt' => null,
            'deliveredAt' => null,
            'deliveredTo' => null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        $this->selectedPackageId = $id;
        $this->mode = 'detail';
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Encomenda registrada',
            'message' => 'Recebimento confirmado. O morador ainda não foi avisado.',
        ];
    }

    public function notifyRecipient(): void
    {
        $package = $this->findPackage($this->selectedPackageId ?? 0);

        if (! $package || $package['status'] !== 'aguardando') {
            return;
        }

        foreach ($this->packages as $index => $item) {
            if ($item['id'] === $package['id']) {
                $this->packages[$index]['status'] = 'avisado';
                $this->packages[$index]['notifiedAt'] = now()->format('d/m/Y H:i');
            }
        }

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Morador avisado',
            'message' => 'Notificação registrada. A encomenda continua armazenada até a retirada.',
        ];
    }

    public function deliverPackage(): void
    {
        $package = $this->findPackage($this->selectedPackageId ?? 0);

        if (! $package || $package['status'] === 'entregue') {
            return;
        }

        $this->validate([
            'deliveredTo' => ['required', 'string', 'max:160'],
            'deliveryNotes' => ['nullable', 'string', 'max:200'],
        ], [
            'required' => 'Informe quem retirou a encomenda.',
        ]);

        foreach ($this->packages as $index => $item) {
            if ($item['id'] === $package['id']) {
                $this->packages[$index]['status'] = 'entregue';
                $this->packages[$index]['deliveredAt'] = now()->format('d/m/Y H:i');
                $this->packages[$index]['deliveredTo'] = $this->deliveredTo;
                if ($this->deliveryNotes !== '') {
                    $this->packages[$index]['notes'] = trim(($item['notes'] ?? '').' '.$this->deliveryNotes);
                }
            }
        }

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

        return array_values(array_filter($this->packages, function (array $package) use ($search): bool {
            $matchesStatus = $this->statusFilter === 'todas' || $package['status'] === $this->statusFilter;
            $matchesSearch = $search === '' || str_contains(mb_strtolower(implode(' ', [
                $package['recipient'], $package['property'], $package['protocol'], $package['carrier'],
            ])), $search);

            return $matchesStatus && $matchesSearch;
        }));
    }

    /** @return array<string, mixed>|null */
    public function selectedPackage(): ?array
    {
        return $this->selectedPackageId ? $this->findPackage($this->selectedPackageId) : null;
    }

    /** @return array<string, mixed>|null */
    private function findPackage(int $id): ?array
    {
        foreach ($this->packages as $package) {
            if ($package['id'] === $id) {
                return $package;
            }
        }

        return null;
    }

    private function resetForm(): void
    {
        $this->recipientName = '';
        $this->property = 'Bloco B — Apto 304';
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
        ])->layout('components.layouts.app', [
            'title' => 'Encomendas',
            'heading' => $this->mode === 'form' ? 'Registrar encomenda' : ($this->mode === 'detail' ? 'Detalhe da encomenda' : 'Encomendas'),
            'headingDescription' => 'Recebimento, armazenamento, aviso e entrega de pacotes',
        ]);
    }
}

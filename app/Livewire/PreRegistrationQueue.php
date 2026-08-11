<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PreRegistrationQueue extends Component
{
    public ?int $editingId = null;

    public string $editName = '';

    public string $editPhone = '';

    public string $editEmail = '';

    public string $editBirthDate = '';

    public string $editAddress = '';

    public string $editDestination = '';

    public string $editResponsible = '';

    public string $editPeriod = '';

    public string $editVehicle = '';

    public string $editReason = '';

    /** @var array<int, array<string, string>> */
    public array $detailOverrides = [];

    /** @var list<array{recordId: int, operator: string, at: string, reason: string, changes: string}> */
    public array $auditLog = [];

    /** @var array<int, array<string, string>> */
    private const DETAILS = [
        1 => ['phone' => '(12) 99876-4321', 'email' => 'camila.andrade@example.com', 'birthDate' => '14/03/1993', 'address' => 'Rua das Palmeiras, 125 · Centro · Taubaté/SP', 'documentStatus' => 'Documento enviado e legível', 'selfieStatus' => 'Selfie enviada e adequada'],
        2 => ['phone' => '(12) 99720-1144', 'email' => 'paulo.lima@example.com', 'birthDate' => '22/08/1987', 'address' => 'Av. Independência, 840 · Taubaté/SP', 'documentStatus' => 'Documento enviado e legível', 'selfieStatus' => 'Selfie enviada e adequada'],
        3 => ['phone' => '(11) 99654-7788', 'email' => 'renata.alves@example.com', 'birthDate' => '05/12/1990', 'address' => 'Rua Bela Cintra, 312 · São Paulo/SP', 'documentStatus' => 'Documento enviado e legível', 'selfieStatus' => 'Reenvio solicitado'],
        4 => ['phone' => '(12) 99118-2020', 'email' => 'felipe.martins@example.com', 'birthDate' => '19/06/1985', 'address' => 'Rua das Acácias, 42 · Taubaté/SP', 'documentStatus' => 'Documento conferido', 'selfieStatus' => 'Selfie conferida'],
        5 => ['phone' => '(12) 98876-1004', 'email' => 'sergio.luz@example.com', 'birthDate' => '30/01/1979', 'address' => 'Rua Projetada, 91 · Pindamonhangaba/SP', 'documentStatus' => 'Documento incompleto', 'selfieStatus' => 'Selfie enviada'],
    ];

    public string $statusFilter = 'aguardando';

    public string $search = '';

    public string $rejectionReason = 'documento_incompleto';

    public string $correctionItems = 'documento';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    /** @var list<array{id: int, name: string, initials: string, type: string, document: string, submittedAt: string, vehicle: string, protocol: string, status: string, destination: string, responsible: string, period: string, alert: string|null}> */
    public array $records = [
        ['id' => 1, 'name' => 'Camila Andrade', 'initials' => 'CA', 'type' => 'Visitante', 'document' => '***.***.331-**', 'submittedAt' => '09/08/2026 às 15:57', 'vehicle' => 'ABC1D23', 'protocol' => 'PRE-SRA-X7K9M2', 'status' => 'aguardando', 'destination' => 'Bloco B · Apto 304', 'responsible' => 'Mariana Souza', 'period' => '10/08/2026 · 18:00 às 22:00', 'alert' => 'Aguardando há mais de 24 horas'],
        ['id' => 2, 'name' => 'Paulo Henrique Lima', 'initials' => 'PH', 'type' => 'Prestador', 'document' => '***.***.760-**', 'submittedAt' => '10/08/2026 às 08:42', 'vehicle' => 'DEF4G56', 'protocol' => 'PRE-SRA-M4N8Q1', 'status' => 'aguardando', 'destination' => 'Área comum · Manutenção', 'responsible' => 'Síndica Ana Ferreira', 'period' => '11/08/2026 · 08:00 às 17:00', 'alert' => null],
        ['id' => 3, 'name' => 'Renata Alves', 'initials' => 'RA', 'type' => 'Turista', 'document' => '***.***.218-**', 'submittedAt' => '10/08/2026 às 10:15', 'vehicle' => 'Sem veículo', 'protocol' => 'PRE-SRA-C2P5T8', 'status' => 'correcao', 'destination' => 'Praia do Santa Rita', 'responsible' => 'Não exige responsável de imóvel', 'period' => '12/08 a 16/08/2026', 'alert' => 'Selfie precisa ser reenviada'],
        ['id' => 4, 'name' => 'Felipe Martins', 'initials' => 'FM', 'type' => 'Visitante', 'document' => '***.***.004-**', 'submittedAt' => '09/08/2026 às 19:21', 'vehicle' => 'GHI7J89', 'protocol' => 'PRE-SRA-R6V3B7', 'status' => 'aprovado', 'destination' => 'Bloco A · Apto 112', 'responsible' => 'Eduardo Nogueira', 'period' => '10/08/2026 · 14:00 às 20:00', 'alert' => null],
        ['id' => 5, 'name' => 'Sérgio Luz', 'initials' => 'SL', 'type' => 'Prestador', 'document' => '***.***.447-**', 'submittedAt' => '08/08/2026 às 16:30', 'vehicle' => 'Sem veículo', 'protocol' => 'PRE-SRA-H9D2K4', 'status' => 'rejeitado', 'destination' => 'Bloco B · Apto 706', 'responsible' => 'Luciana Ferraz', 'period' => '09/08/2026 · 09:00 às 12:00', 'alert' => null],
    ];

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['aguardando', 'aprovado', 'rejeitado', 'todos'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function approve(int $id): void
    {
        if ($this->editingId === $id) {
            $this->addError('editReason', 'Salve ou cancele a edição antes de aprovar.');

            return;
        }

        $this->updateStatus($id, 'aprovado');
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Pré-cadastro aprovado',
            'message' => 'A entrada ainda será validada pela portaria conforme as regras vigentes.',
        ];
    }

    public function beginEdit(int $id): void
    {
        $record = collect($this->records)->firstWhere('id', $id);

        if (! $record || $record['status'] !== 'aguardando') {
            return;
        }

        $details = $this->detailsFor($id);
        $this->editingId = $id;
        $this->editName = $record['name'];
        $this->editPhone = $details['phone'];
        $this->editEmail = $details['email'];
        $this->editBirthDate = $details['birthDate'];
        $this->editAddress = $details['address'];
        $this->editDestination = $record['destination'];
        $this->editResponsible = $record['responsible'];
        $this->editPeriod = $record['period'];
        $this->editVehicle = $record['vehicle'];
        $this->editReason = '';
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editReason = '';
        $this->resetErrorBag();
    }

    public function saveEdit(int $id): void
    {
        if ($this->editingId !== $id) {
            return;
        }

        $this->validate([
            'editName' => ['required', 'string', 'min:3', 'max:120'],
            'editPhone' => ['required', 'string', 'min:8', 'max:20'],
            'editEmail' => ['required', 'email', 'max:120'],
            'editBirthDate' => ['required', 'date_format:d/m/Y'],
            'editAddress' => ['required', 'string', 'max:200'],
            'editDestination' => ['required', 'string', 'max:120'],
            'editResponsible' => ['required', 'string', 'max:120'],
            'editPeriod' => ['required', 'string', 'max:120'],
            'editVehicle' => ['required', 'string', 'max:80'],
            'editReason' => ['required', 'string', 'min:5', 'max:200'],
        ], ['required' => 'Preencha este campo antes de salvar.', 'email' => 'Informe um e-mail válido.']);

        foreach ($this->records as $index => $record) {
            if ($record['id'] !== $id) {
                continue;
            }

            $changes = [];
            foreach (['name' => 'editName', 'destination' => 'editDestination', 'responsible' => 'editResponsible', 'period' => 'editPeriod', 'vehicle' => 'editVehicle'] as $field => $property) {
                if ($record[$field] !== $this->{$property}) {
                    $changes[] = "{$field}: {$record[$field]} → {$this->{$property}}";
                    $this->records[$index][$field] = $this->{$property};
                }
            }

            $details = $this->detailsFor($id);
            foreach (['phone' => 'editPhone', 'email' => 'editEmail', 'birthDate' => 'editBirthDate', 'address' => 'editAddress'] as $field => $property) {
                if ($details[$field] !== $this->{$property}) {
                    $changes[] = "{$field}: {$details[$field]} → {$this->{$property}}";
                }
                $this->detailOverrides[$id][$field] = $this->{$property};
            }

            $this->auditLog[] = [
                'recordId' => $id,
                'operator' => 'Tatiane Souza',
                'at' => '11/08/2026 às 10:30',
                'reason' => $this->editReason,
                'changes' => $changes === [] ? 'Dados revisados sem alteração de valor.' : implode(' | ', $changes),
            ];
            break;
        }

        $this->editingId = null;
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Correção salva com auditoria',
            'message' => 'A versão original foi preservada e as alterações estão prontas para nova conferência antes da aprovação.',
        ];
    }

    /** @return array<string, string> */
    public function detailsFor(int $id): array
    {
        return array_merge(self::DETAILS[$id] ?? self::DETAILS[1], $this->detailOverrides[$id] ?? []);
    }

    /** @return list<array{recordId: int, operator: string, at: string, reason: string, changes: string}> */
    public function auditEntriesFor(int $id): array
    {
        return array_values(array_filter($this->auditLog, fn (array $entry): bool => $entry['recordId'] === $id));
    }

    public function reject(int $id): void
    {
        $this->validate([
            'rejectionReason' => ['required', Rule::in(['documento_incompleto', 'dados_divergentes', 'periodo_invalido', 'solicitacao_nao_confirmada'])],
        ]);

        $this->updateStatus($id, 'rejeitado');
        $this->feedback = [
            'variant' => 'danger',
            'title' => 'Pré-cadastro rejeitado',
            'message' => 'A decisão foi registrada. A observação interna não será enviada ao solicitante.',
        ];
    }

    public function requestCorrection(int $id): void
    {
        $this->validate([
            'correctionItems' => ['required', Rule::in(['dados_pessoais', 'documento', 'selfie', 'veiculo'])],
        ]);

        $this->updateStatus($id, 'correcao');
        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Correção solicitada',
            'message' => 'O item selecionado foi enviado para correção, preservando a versão analisada.',
        ];
    }

    /** @return list<array{id: int, name: string, initials: string, type: string, document: string, submittedAt: string, vehicle: string, protocol: string, status: string, destination: string, responsible: string, period: string, alert: string|null}> */
    public function filteredRecords(): array
    {
        $search = mb_strtolower(trim($this->search));

        return array_values(array_filter($this->records, function (array $record) use ($search): bool {
            $matchesStatus = $this->statusFilter === 'todos' || $record['status'] === $this->statusFilter;
            $matchesSearch = $search === '' || str_contains(mb_strtolower(implode(' ', [$record['name'], $record['protocol'], $record['vehicle'], $record['destination']])), $search);

            return $matchesStatus && $matchesSearch;
        }));
    }

    private function updateStatus(int $id, string $status): void
    {
        foreach ($this->records as $index => $record) {
            if ($record['id'] === $id) {
                $this->records[$index]['status'] = $status;

                return;
            }
        }
    }

    public function render(): View
    {
        return view('livewire.pre-registration-queue', [
            'filteredRecords' => $this->filteredRecords(),
        ])->layout('components.layouts.app', [
            'title' => 'Pré-cadastros',
            'heading' => 'Pré-cadastros',
            'headingDescription' => 'Solicitações antecipadas aguardando análise da portaria',
        ]);
    }
}

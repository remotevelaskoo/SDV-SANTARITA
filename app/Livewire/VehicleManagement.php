<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class VehicleManagement extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todos';

    public string $typeFilter = 'todos';

    public ?int $selectedVehicleId = null;

    public ?int $editingVehicleId = null;

    public string $plate = '';

    public string $type = 'carro';

    public string $brand = '';

    public string $model = '';

    public string $color = '';

    public string $year = '';

    public string $renavam = '';

    public string $owner = '';

    public string $ownerDocument = '';

    public string $propertyCode = '';

    public string $relationship = 'proprietario';

    public string $vehicleStatus = 'pendente';

    public string $accessUse = 'morador';

    public string $notes = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    /** @var list<array<string, mixed>> */
    public array $vehicles = [
        [
            'id' => 1, 'plate' => 'ABC1D23', 'type' => 'carro', 'brand' => 'Toyota', 'model' => 'Corolla XEi', 'color' => 'Prata', 'year' => '2022', 'renavam' => '*******4821', 'status' => 'ativo', 'accessUse' => 'morador', 'owner' => 'Marcos Vinicius da Silva', 'ownerDocument' => '***.654.321-**', 'propertyCode' => 'SRA-A-102', 'relationship' => 'Pessoa e imóvel', 'updated' => '10/08/2026 às 17:18', 'lprStatus' => 'sincronizado', 'lastReading' => '10/08/2026 às 17:42 · Portão social', 'alert' => null,
        ],
        [
            'id' => 2, 'plate' => 'DEF4G56', 'type' => 'carro', 'brand' => 'Honda', 'model' => 'HR-V Touring', 'color' => 'Branco', 'year' => '2023', 'renavam' => '*******1934', 'status' => 'ativo', 'accessUse' => 'morador', 'owner' => 'Fernanda da Silva', 'ownerDocument' => '***.218.904-**', 'propertyCode' => 'SRA-A-102', 'relationship' => 'Pessoa e imóvel', 'updated' => '10/08/2026 às 15:35', 'lprStatus' => 'sincronizado', 'lastReading' => '09/08/2026 às 21:06 · Garagem', 'alert' => null,
        ],
        [
            'id' => 3, 'plate' => 'GHI7J89', 'type' => 'carro', 'brand' => 'Volkswagen', 'model' => 'T-Cross Comfortline', 'color' => 'Cinza', 'year' => '2021', 'renavam' => '*******7508', 'status' => 'ativo', 'accessUse' => 'morador', 'owner' => 'Bianca Moretti', 'ownerDocument' => '***.615.338-**', 'propertyCode' => 'SRA-A-208', 'relationship' => 'Pessoa e imóvel', 'updated' => '09/08/2026 às 18:02', 'lprStatus' => 'revisao', 'lastReading' => '08/08/2026 às 08:11 · Garagem', 'alert' => 'Leitura da placa requer revisão visual',
        ],
        [
            'id' => 4, 'plate' => 'JKL2M34', 'type' => 'moto', 'brand' => 'Honda', 'model' => 'CG 160 Titan', 'color' => 'Vermelho', 'year' => '2024', 'renavam' => '*******2240', 'status' => 'pendente', 'accessUse' => 'prestador', 'owner' => 'Eduardo Nunes', 'ownerDocument' => '***.904.117-**', 'propertyCode' => 'SRA-B-304', 'relationship' => 'Pessoa autorizada', 'updated' => '08/08/2026 às 12:26', 'lprStatus' => 'nao_sincronizado', 'lastReading' => 'Nenhuma leitura registrada', 'alert' => 'Vínculo aguardando conferência da administração',
        ],
        [
            'id' => 5, 'plate' => 'MNO5P67', 'type' => 'utilitario', 'brand' => 'Fiat', 'model' => 'Fiorino Endurance', 'color' => 'Branco', 'year' => '2020', 'renavam' => '*******6612', 'status' => 'bloqueado', 'accessUse' => 'prestador', 'owner' => 'Vale Serviços Ltda.', 'ownerDocument' => '**.***.482/****-**', 'propertyCode' => 'Sem vínculo fixo', 'relationship' => 'Empresa prestadora', 'updated' => '07/08/2026 às 09:41', 'lprStatus' => 'suspenso', 'lastReading' => '02/08/2026 às 14:20 · Portão de serviço', 'alert' => 'Veículo bloqueado para novas autorizações',
        ],
    ];

    public function openVehicle(int $id): void
    {
        if ($this->findVehicle($id)) {
            $this->selectedVehicleId = $id;
            $this->mode = 'detail';
            $this->feedback = null;
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->selectedVehicleId = null;
        $this->editingVehicleId = null;
        $this->feedback = null;
        $this->resetErrorBag();
    }

    public function createVehicle(): void
    {
        $this->resetForm();
        $this->mode = 'form';
    }

    public function editVehicle(int $id): void
    {
        $vehicle = $this->findVehicle($id);

        if (! $vehicle) {
            return;
        }

        $this->editingVehicleId = $id;
        $this->plate = $vehicle['plate'];
        $this->type = $vehicle['type'];
        $this->brand = $vehicle['brand'];
        $this->model = $vehicle['model'];
        $this->color = $vehicle['color'];
        $this->year = $vehicle['year'];
        $this->renavam = '';
        $this->owner = $vehicle['owner'];
        $this->ownerDocument = '';
        $this->propertyCode = $vehicle['propertyCode'] === 'Sem vínculo fixo' ? '' : $vehicle['propertyCode'];
        $this->relationship = match ($vehicle['relationship']) {
            'Pessoa e imóvel' => 'proprietario',
            'Empresa prestadora' => 'empresa',
            default => 'autorizado',
        };
        $this->vehicleStatus = $vehicle['status'];
        $this->accessUse = $vehicle['accessUse'];
        $this->notes = '';
        $this->mode = 'form';
        $this->feedback = null;
    }

    public function saveDraft(): void
    {
        $this->plate = $this->normalizePlate($this->plate);
        $this->validate([
            'plate' => ['required', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/'],
            'model' => ['required', 'string', 'max:80'],
        ], [
            'required' => 'Preencha a placa e o modelo para salvar o rascunho.',
            'plate.regex' => 'Informe uma placa brasileira válida, como ABC1D23 ou ABC1234.',
        ]);

        $this->vehicleStatus = 'pendente';
        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Rascunho preservado',
            'message' => 'O veículo permanece pendente e não foi autorizado para acesso.',
        ];
    }

    public function saveVehicle(): void
    {
        $this->plate = $this->normalizePlate($this->plate);
        $this->validateVehicle();

        foreach ($this->vehicles as $vehicle) {
            if ($vehicle['plate'] === $this->plate && $vehicle['id'] !== $this->editingVehicleId) {
                $this->addError('plate', 'Esta placa já está cadastrada no sistema.');

                return;
            }
        }

        $relationshipLabel = match ($this->relationship) {
            'empresa' => 'Empresa prestadora',
            'autorizado' => 'Pessoa autorizada',
            default => 'Pessoa e imóvel',
        };
        $propertyLabel = $this->propertyCode !== '' ? strtoupper($this->propertyCode) : 'Sem vínculo fixo';
        $renavamMasked = $this->renavam !== '' ? '*******'.substr($this->renavam, -4) : 'Não informado';

        $data = [
            'plate' => $this->plate,
            'type' => $this->type,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'year' => $this->year,
            'renavam' => $renavamMasked,
            'status' => $this->vehicleStatus,
            'accessUse' => $this->accessUse,
            'owner' => $this->owner,
            'ownerDocument' => $this->maskDocument($this->ownerDocument),
            'propertyCode' => $propertyLabel,
            'relationship' => $relationshipLabel,
            'updated' => '10/08/2026 às 19:24',
        ];

        if ($this->editingVehicleId) {
            foreach ($this->vehicles as $index => $vehicle) {
                if ($vehicle['id'] === $this->editingVehicleId) {
                    if ($this->renavam === '') {
                        $data['renavam'] = $vehicle['renavam'];
                    }

                    if ($this->ownerDocument === '') {
                        $data['ownerDocument'] = $vehicle['ownerDocument'];
                    }

                    $this->vehicles[$index] = array_merge($vehicle, $data);
                }
            }
            $id = $this->editingVehicleId;
        } else {
            $id = max(array_column($this->vehicles, 'id')) + 1;
            $this->vehicles[] = array_merge($data, [
                'id' => $id,
                'lprStatus' => 'nao_sincronizado',
                'lastReading' => 'Nenhuma leitura registrada',
                'alert' => $this->vehicleStatus === 'ativo' ? null : 'Cadastro aguardando liberação ou conferência',
            ]);
        }

        $this->selectedVehicleId = $id;
        $this->mode = 'detail';
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Veículo salvo',
            'message' => 'O cadastro foi atualizado. Pessoa, imóvel, situação e autorização continuam independentes.',
        ];
    }

    public function toggleVehicleBlock(): void
    {
        if (! $this->selectedVehicleId) {
            return;
        }

        foreach ($this->vehicles as $index => $vehicle) {
            if ($vehicle['id'] === $this->selectedVehicleId) {
                $newStatus = $vehicle['status'] === 'bloqueado' ? 'ativo' : 'bloqueado';
                $this->vehicles[$index]['status'] = $newStatus;
                $this->vehicles[$index]['lprStatus'] = $newStatus === 'bloqueado' ? 'suspenso' : 'nao_sincronizado';
                $this->vehicles[$index]['alert'] = $newStatus === 'bloqueado' ? 'Veículo bloqueado para novas autorizações' : null;
                $this->feedback = [
                    'variant' => $newStatus === 'bloqueado' ? 'warning' : 'success',
                    'title' => $newStatus === 'bloqueado' ? 'Veículo bloqueado' : 'Veículo reativado',
                    'message' => 'O histórico e os vínculos foram preservados. A liberação de entrada continua sendo uma decisão separada.',
                ];
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function filteredVehicles(): array
    {
        $search = mb_strtolower(trim($this->search));

        return array_values(array_filter($this->vehicles, function (array $vehicle) use ($search): bool {
            $matchesStatus = $this->statusFilter === 'todos' || $vehicle['status'] === $this->statusFilter;
            $matchesType = $this->typeFilter === 'todos' || $vehicle['type'] === $this->typeFilter;
            $haystack = implode(' ', [$vehicle['plate'], $vehicle['brand'], $vehicle['model'], $vehicle['owner'], $vehicle['propertyCode']]);
            $matchesSearch = $search === '' || str_contains(mb_strtolower($haystack), $search);

            return $matchesStatus && $matchesType && $matchesSearch;
        }));
    }

    /** @return array<string, mixed>|null */
    public function selectedVehicle(): ?array
    {
        return $this->selectedVehicleId ? $this->findVehicle($this->selectedVehicleId) : null;
    }

    /** @return array<string, mixed>|null */
    private function findVehicle(int $id): ?array
    {
        foreach ($this->vehicles as $vehicle) {
            if ($vehicle['id'] === $id) {
                return $vehicle;
            }
        }

        return null;
    }

    private function resetForm(): void
    {
        $this->editingVehicleId = null;
        $this->plate = '';
        $this->type = 'carro';
        $this->brand = '';
        $this->model = '';
        $this->color = '';
        $this->year = '';
        $this->renavam = '';
        $this->owner = '';
        $this->ownerDocument = '';
        $this->propertyCode = '';
        $this->relationship = 'proprietario';
        $this->vehicleStatus = 'pendente';
        $this->accessUse = 'morador';
        $this->notes = '';
        $this->feedback = null;
        $this->resetErrorBag();
    }

    private function validateVehicle(): void
    {
        $this->validate([
            'plate' => ['required', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/'],
            'type' => ['required', Rule::in(['carro', 'moto', 'utilitario', 'caminhao', 'outro'])],
            'brand' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'max:40'],
            'year' => ['required', 'integer', 'between:1900,2027'],
            'renavam' => ['nullable', 'digits_between:9,11'],
            'owner' => ['required', 'string', 'max:120'],
            'ownerDocument' => ['nullable', 'string', 'max:20'],
            'propertyCode' => ['nullable', 'string', 'max:40'],
            'relationship' => ['required', Rule::in(['proprietario', 'autorizado', 'empresa'])],
            'vehicleStatus' => ['required', Rule::in(['pendente', 'ativo', 'inativo', 'bloqueado'])],
            'accessUse' => ['required', Rule::in(['morador', 'visitante', 'prestador', 'administrativo'])],
            'notes' => ['nullable', 'string', 'max:300'],
        ], [
            'required' => 'Preencha este campo para salvar o veículo.',
            'plate.regex' => 'Informe uma placa brasileira válida, como ABC1D23 ou ABC1234.',
            'year.between' => 'Informe um ano válido para o veículo.',
            'renavam.digits_between' => 'O RENAVAM deve conter de 9 a 11 números.',
        ]);
    }

    private function normalizePlate(string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate) ?? '');
    }

    private function maskDocument(string $document): string
    {
        $digits = preg_replace('/\D/', '', $document) ?? '';

        if ($digits === '') {
            return 'Não informado';
        }

        return strlen($digits) > 11 ? '**.***.'.substr($digits, -6, 3).'/****-**' : '***.***.'.substr($digits, -5, 3).'-**';
    }

    public function render(): View
    {
        return view('livewire.vehicle-management', [
            'filteredVehicles' => $this->filteredVehicles(),
            'selectedVehicle' => $this->selectedVehicle(),
        ])->layout('components.layouts.app', [
            'title' => 'Veículos',
            'heading' => $this->mode === 'form' ? ($this->editingVehicleId ? 'Editar veículo' : 'Cadastrar veículo') : ($this->mode === 'detail' ? 'Detalhe do veículo' : 'Veículos'),
            'headingDescription' => 'Placas, características, proprietários e vínculos do Condomínio Santa Rita',
        ]);
    }
}

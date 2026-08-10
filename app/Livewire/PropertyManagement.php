<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PropertyManagement extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todos';

    public ?int $selectedPropertyId = null;

    public ?int $editingPropertyId = null;

    public string $organization = 'Condomínio Santa Rita';

    public string $block = '';

    public string $unit = '';

    public string $code = '';

    public string $zipCode = '';

    public string $street = '';

    public string $number = '';

    public string $complement = '';

    public string $district = '';

    public string $city = 'Taubaté';

    public string $state = 'SP';

    public string $propertyStatus = 'implantacao';

    public string $notes = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    /** @var list<array<string, mixed>> */
    public array $properties = [
        [
            'id' => 1, 'code' => 'SRA-A-102', 'block' => 'A', 'unit' => '102', 'address' => 'Rua das Acácias, 100 · Jardim das Flores', 'status' => 'ativo', 'responsible' => 'Marcos Vinicius da Silva', 'occupants' => 3, 'vehicles' => 2, 'updated' => '10/08/2026 às 16:30', 'alert' => null,
            'links' => [
                ['name' => 'Marcos Vinicius da Silva', 'document' => '***.654.321-**', 'nature' => 'Proprietário', 'role' => 'Titular', 'responsibility' => 'Responsável principal', 'validity' => 'Prazo indeterminado', 'status' => 'ativo'],
                ['name' => 'Fernanda da Silva', 'document' => '***.218.904-**', 'nature' => 'Moradora', 'role' => 'Cônjuge', 'responsibility' => 'Responsável adicional', 'validity' => 'Prazo indeterminado', 'status' => 'ativo'],
                ['name' => 'Lucas da Silva', 'document' => '***.760.115-**', 'nature' => 'Morador', 'role' => 'Dependente', 'responsibility' => 'Sem responsabilidade administrativa', 'validity' => 'Prazo indeterminado', 'status' => 'ativo'],
            ],
            'vehicleList' => [
                ['plate' => 'ABC1D23', 'model' => 'Toyota Corolla', 'color' => 'Prata', 'owner' => 'Marcos Vinicius', 'link' => 'Pessoa e imóvel', 'status' => 'ativo'],
                ['plate' => 'DEF4G56', 'model' => 'Honda HR-V', 'color' => 'Branco', 'owner' => 'Fernanda da Silva', 'link' => 'Pessoa e imóvel', 'status' => 'ativo'],
            ],
        ],
        [
            'id' => 2, 'code' => 'SRA-A-208', 'block' => 'A', 'unit' => '208', 'address' => 'Rua das Acácias, 100 · Jardim das Flores', 'status' => 'ativo', 'responsible' => 'Bianca Moretti', 'occupants' => 2, 'vehicles' => 1, 'updated' => '10/08/2026 às 14:12', 'alert' => 'Contrato de locação vence em 28 dias',
            'links' => [
                ['name' => 'Bianca Moretti', 'document' => '***.615.338-**', 'nature' => 'Inquilina', 'role' => 'Titular', 'responsibility' => 'Responsável principal', 'validity' => 'Até 07/09/2026', 'status' => 'ativo'],
                ['name' => 'André Moretti', 'document' => '***.447.901-**', 'nature' => 'Morador', 'role' => 'Cônjuge', 'responsibility' => 'Sem responsabilidade administrativa', 'validity' => 'Até 07/09/2026', 'status' => 'ativo'],
            ],
            'vehicleList' => [
                ['plate' => 'GHI7J89', 'model' => 'Volkswagen T-Cross', 'color' => 'Cinza', 'owner' => 'Bianca Moretti', 'link' => 'Pessoa e imóvel', 'status' => 'ativo'],
            ],
        ],
        [
            'id' => 3, 'code' => 'SRA-B-304', 'block' => 'B', 'unit' => '304', 'address' => 'Rua das Acácias, 120 · Jardim das Flores', 'status' => 'ativo', 'responsible' => 'Mariana Souza', 'occupants' => 4, 'vehicles' => 2, 'updated' => '09/08/2026 às 18:44', 'alert' => null,
            'links' => [
                ['name' => 'Mariana Souza', 'document' => '***.331.407-**', 'nature' => 'Proprietária', 'role' => 'Titular', 'responsibility' => 'Responsável principal', 'validity' => 'Prazo indeterminado', 'status' => 'ativo'],
            ],
            'vehicleList' => [],
        ],
        [
            'id' => 4, 'code' => 'SRA-C-501', 'block' => 'C', 'unit' => '501', 'address' => 'Rua das Palmeiras, 50 · Jardim das Flores', 'status' => 'bloqueado', 'responsible' => 'Rafael Domingues', 'occupants' => 1, 'vehicles' => 1, 'updated' => '08/08/2026 às 11:05', 'alert' => 'Imóvel bloqueado para novas autorizações',
            'links' => [
                ['name' => 'Rafael Domingues', 'document' => '***.004.120-**', 'nature' => 'Proprietário', 'role' => 'Titular', 'responsibility' => 'Responsável principal', 'validity' => 'Prazo indeterminado', 'status' => 'ativo'],
            ],
            'vehicleList' => [],
        ],
        [
            'id' => 5, 'code' => 'SRA-C-706', 'block' => 'C', 'unit' => '706', 'address' => 'Rua das Palmeiras, 50 · Jardim das Flores', 'status' => 'implantacao', 'responsible' => 'Não definido', 'occupants' => 0, 'vehicles' => 0, 'updated' => '07/08/2026 às 09:20', 'alert' => 'Responsável principal ainda não definido',
            'links' => [], 'vehicleList' => [],
        ],
    ];

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['todos', 'ativo', 'implantacao', 'inativo', 'bloqueado'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function openProperty(int $id): void
    {
        if ($this->findProperty($id)) {
            $this->selectedPropertyId = $id;
            $this->mode = 'detail';
            $this->feedback = null;
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->selectedPropertyId = null;
        $this->editingPropertyId = null;
    }

    public function createProperty(): void
    {
        $this->resetForm();
        $this->mode = 'form';
    }

    public function editProperty(int $id): void
    {
        $property = $this->findProperty($id);

        if (! $property) {
            return;
        }

        $this->editingPropertyId = $id;
        $this->organization = 'Condomínio Santa Rita';
        $this->block = $property['block'];
        $this->unit = $property['unit'];
        $this->code = $property['code'];
        $this->zipCode = '12000-000';
        $this->street = str($property['address'])->before(',')->toString();
        $this->number = str($property['address'])->after(', ')->before(' ·')->toString();
        $this->district = str($property['address'])->after('· ')->toString();
        $this->city = 'Taubaté';
        $this->state = 'SP';
        $this->propertyStatus = $property['status'];
        $this->notes = '';
        $this->mode = 'form';
        $this->feedback = null;
    }

    public function saveDraft(): void
    {
        $this->validate([
            'unit' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:40'],
        ], ['required' => 'Preencha este campo para salvar o rascunho.']);

        $this->propertyStatus = 'implantacao';
        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Rascunho preservado',
            'message' => 'O imóvel permanece em implantação. Nenhum vínculo ou acesso foi ativado.',
        ];
    }

    public function saveProperty(): void
    {
        $this->validateProperty();

        foreach ($this->properties as $property) {
            if ($property['block'] === strtoupper($this->block) && $property['unit'] === $this->unit && $property['id'] !== $this->editingPropertyId) {
                $this->addError('unit', 'Já existe um imóvel com este bloco e unidade.');

                return;
            }
        }

        $address = "{$this->street}, {$this->number}".($this->complement !== '' ? " · {$this->complement}" : '')." · {$this->district}";

        if ($this->editingPropertyId) {
            foreach ($this->properties as $index => $property) {
                if ($property['id'] === $this->editingPropertyId) {
                    $this->properties[$index] = array_merge($property, [
                        'code' => strtoupper($this->code), 'block' => strtoupper($this->block), 'unit' => $this->unit, 'address' => $address, 'status' => $this->propertyStatus, 'updated' => '10/08/2026 às 18:42',
                    ]);
                }
            }
            $id = $this->editingPropertyId;
        } else {
            $id = max(array_column($this->properties, 'id')) + 1;
            $this->properties[] = [
                'id' => $id, 'code' => strtoupper($this->code), 'block' => strtoupper($this->block), 'unit' => $this->unit, 'address' => $address, 'status' => $this->propertyStatus, 'responsible' => 'Não definido', 'occupants' => 0, 'vehicles' => 0, 'updated' => '10/08/2026 às 18:42', 'alert' => 'Responsável principal ainda não definido', 'links' => [], 'vehicleList' => [],
            ];
        }

        $this->selectedPropertyId = $id;
        $this->mode = 'detail';
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Imóvel salvo',
            'message' => 'Os dados estruturais foram registrados. Pessoas, vínculos e autorizações permanecem separados.',
        ];
    }

    public function togglePropertyBlock(): void
    {
        if (! $this->selectedPropertyId) {
            return;
        }

        foreach ($this->properties as $index => $property) {
            if ($property['id'] === $this->selectedPropertyId) {
                $newStatus = $property['status'] === 'bloqueado' ? 'ativo' : 'bloqueado';
                $this->properties[$index]['status'] = $newStatus;
                $this->properties[$index]['alert'] = $newStatus === 'bloqueado' ? 'Imóvel bloqueado para novas autorizações' : null;
                $this->feedback = [
                    'variant' => $newStatus === 'bloqueado' ? 'warning' : 'success',
                    'title' => $newStatus === 'bloqueado' ? 'Imóvel bloqueado' : 'Imóvel reativado',
                    'message' => 'A situação estrutural foi alterada. Os vínculos individuais continuam preservados para análise própria.',
                ];
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function filteredProperties(): array
    {
        $search = mb_strtolower(trim($this->search));

        return array_values(array_filter($this->properties, function (array $property) use ($search): bool {
            $matchesStatus = $this->statusFilter === 'todos' || $property['status'] === $this->statusFilter;
            $matchesSearch = $search === '' || str_contains(mb_strtolower(implode(' ', [$property['code'], $property['block'], $property['unit'], $property['address'], $property['responsible']])), $search);

            return $matchesStatus && $matchesSearch;
        }));
    }

    /** @return array<string, mixed>|null */
    public function selectedProperty(): ?array
    {
        return $this->selectedPropertyId ? $this->findProperty($this->selectedPropertyId) : null;
    }

    /** @return array<string, mixed>|null */
    private function findProperty(int $id): ?array
    {
        foreach ($this->properties as $property) {
            if ($property['id'] === $id) {
                return $property;
            }
        }

        return null;
    }

    private function resetForm(): void
    {
        $this->editingPropertyId = null;
        $this->organization = 'Condomínio Santa Rita';
        $this->block = '';
        $this->unit = '';
        $this->code = '';
        $this->zipCode = '';
        $this->street = '';
        $this->number = '';
        $this->complement = '';
        $this->district = '';
        $this->city = 'Taubaté';
        $this->state = 'SP';
        $this->propertyStatus = 'implantacao';
        $this->notes = '';
        $this->feedback = null;
        $this->resetErrorBag();
    }

    private function validateProperty(): void
    {
        $this->validate([
            'organization' => ['required', 'string', 'max:120'],
            'block' => ['nullable', 'string', 'max:20'],
            'unit' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:40'],
            'zipCode' => ['required', 'string', 'min:8', 'max:9'],
            'street' => ['required', 'string', 'max:160'],
            'number' => ['required', 'string', 'max:20'],
            'district' => ['required', 'string', 'max:80'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'size:2'],
            'propertyStatus' => ['required', Rule::in(['implantacao', 'ativo', 'inativo', 'bloqueado'])],
            'notes' => ['nullable', 'string', 'max:300'],
        ], [
            'required' => 'Preencha este campo para salvar o imóvel.',
            'size' => 'Use a sigla do estado com duas letras.',
        ]);
    }

    public function render(): View
    {
        return view('livewire.property-management', [
            'filteredProperties' => $this->filteredProperties(),
            'selectedProperty' => $this->selectedProperty(),
        ])->layout('components.layouts.app', [
            'title' => 'Imóveis',
            'heading' => $this->mode === 'form' ? ($this->editingPropertyId ? 'Editar imóvel' : 'Cadastrar imóvel') : ($this->mode === 'detail' ? 'Detalhe do imóvel' : 'Imóveis'),
            'headingDescription' => 'Estrutura, endereço, ocupação e vínculos do Condomínio Santa Rita',
        ]);
    }
}

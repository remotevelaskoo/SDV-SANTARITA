<?php

namespace App\Livewire;

use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\Veiculo;
use App\Models\VeiculoVinculo;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class VehicleManagement extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todos';

    public string $typeFilter = 'todos';

    public ?string $selectedVehicleId = null;

    public ?string $editingVehicleId = null;

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

    public function openVehicle(string $id): void
    {
        if (Veiculo::query()->whereKey($id)->exists()) {
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

    public function editVehicle(string $id): void
    {
        $veiculo = Veiculo::query()->find($id);

        if (! $veiculo) {
            return;
        }

        $vinculo = $veiculo->vinculos()->whereNull('ended_at')->with(['pessoa', 'imovel'])->first();

        $this->editingVehicleId = $id;
        $this->plate = $veiculo->plate_display;
        $this->type = $veiculo->type;
        $this->brand = $veiculo->brand ?? '';
        $this->model = $veiculo->model ?? '';
        $this->color = $veiculo->color ?? '';
        $this->year = '';
        $this->renavam = '';
        $this->owner = $vinculo?->pessoa?->nomeExibicao() ?? '';
        $this->ownerDocument = '';
        $this->propertyCode = $vinculo?->imovel?->codigo ?? '';
        $this->relationship = $vinculo?->tipo ?? 'proprietario';
        $this->vehicleStatus = $veiculo->status;
        $this->accessUse = 'morador';
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

        $plateNormalized = Veiculo::normalizePlate($this->plate);

        $duplicate = Veiculo::query()
            ->where('plate_normalized', $plateNormalized)
            ->when($this->editingVehicleId, fn ($query) => $query->where('id', '!=', $this->editingVehicleId))
            ->exists();

        if ($duplicate) {
            $this->addError('plate', 'Esta placa já está cadastrada no sistema.');

            return;
        }

        $pessoa = Pessoa::query()->whereRaw('LOWER(nome) = ?', [mb_strtolower(trim($this->owner))])->first();

        if (! $pessoa) {
            $this->addError('owner', 'Pessoa não encontrada. Cadastre a pessoa antes de vincular o veículo.');

            return;
        }

        $imovelId = null;

        if (trim($this->propertyCode) !== '') {
            $imovel = Imovel::query()->where('codigo', strtoupper(trim($this->propertyCode)))->first();

            if (! $imovel) {
                $this->addError('propertyCode', 'Imóvel não encontrado com este código.');

                return;
            }

            $imovelId = $imovel->id;
        }

        $dados = [
            'plate_display' => $this->plate,
            'plate_normalized' => $plateNormalized,
            'country' => 'BR',
            'type' => $this->type,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'status' => $this->vehicleStatus,
        ];

        if ($this->editingVehicleId) {
            $veiculo = Veiculo::query()->findOrFail($this->editingVehicleId);
            $veiculo->update($dados);
        } else {
            $veiculo = Veiculo::query()->create($dados);
        }

        $vinculoDados = [
            'pessoa_id' => $pessoa->id,
            'imovel_id' => $imovelId,
            'tipo' => $this->relationship,
            'status' => 'ativo',
        ];

        $vinculo = $veiculo->vinculos()->whereNull('ended_at')->first();

        if ($vinculo) {
            $vinculo->update([...$vinculoDados, 'versao' => $vinculo->versao + 1]);
        } else {
            VeiculoVinculo::query()->create([...$vinculoDados, 'veiculo_id' => $veiculo->id, 'started_at' => now(), 'versao' => 1]);
        }

        $this->selectedVehicleId = $veiculo->id;
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

        $veiculo = Veiculo::query()->find($this->selectedVehicleId);

        if (! $veiculo) {
            return;
        }

        $newStatus = $veiculo->status === 'bloqueado' ? 'ativo' : 'bloqueado';
        $veiculo->update(['status' => $newStatus]);

        $this->feedback = [
            'variant' => $newStatus === 'bloqueado' ? 'warning' : 'success',
            'title' => $newStatus === 'bloqueado' ? 'Veículo bloqueado' : 'Veículo reativado',
            'message' => 'O histórico e os vínculos foram preservados. A liberação de entrada continua sendo uma decisão separada.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function filteredVehicles(): array
    {
        $search = mb_strtolower(trim($this->search));

        $query = Veiculo::query();

        if ($this->statusFilter !== 'todos') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter !== 'todos') {
            $query->where('type', $this->typeFilter);
        }

        $veiculos = $query->orderBy('plate_display')->get();

        if ($search !== '') {
            $veiculos = $veiculos->filter(function (Veiculo $veiculo) use ($search): bool {
                $vinculo = $veiculo->vinculos()->whereNull('ended_at')->with(['pessoa', 'imovel'])->first();
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $veiculo->plate_display, $veiculo->brand, $veiculo->model,
                    $vinculo?->pessoa?->nomeExibicao(), $vinculo?->imovel?->codigo,
                ])));

                return str_contains($haystack, $search);
            });
        }

        return $veiculos->map(fn (Veiculo $veiculo) => $this->toArray($veiculo))->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function selectedVehicle(): ?array
    {
        if ($this->selectedVehicleId === null) {
            return null;
        }

        $veiculo = Veiculo::query()->find($this->selectedVehicleId);

        return $veiculo ? $this->toArray($veiculo) : null;
    }

    /** @return array{active: int, pending: int, blocked: int, synced: int} */
    public function vehicleCounts(): array
    {
        return [
            'active' => Veiculo::query()->where('status', 'ativo')->count(),
            'pending' => Veiculo::query()->where('status', 'pendente')->count(),
            'blocked' => Veiculo::query()->where('status', 'bloqueado')->count(),
            'synced' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function toArray(Veiculo $veiculo): array
    {
        $vinculo = $veiculo->vinculos()->whereNull('ended_at')->with(['pessoa.documentos', 'imovel'])->first();

        $relationshipLabel = match ($vinculo?->tipo) {
            'empresa' => 'Empresa prestadora',
            'autorizado' => 'Pessoa autorizada',
            'proprietario' => 'Pessoa e imóvel',
            default => 'Sem vínculo registrado',
        };

        return [
            'id' => $veiculo->id,
            'plate' => $veiculo->plate_display,
            'type' => $veiculo->type,
            'brand' => $veiculo->brand ?? '—',
            'model' => $veiculo->model ?? '—',
            'color' => $veiculo->color ?? '—',
            'year' => '—',
            'renavam' => 'Não informado',
            'status' => $veiculo->status,
            'accessUse' => 'morador',
            'owner' => $vinculo?->pessoa?->nomeExibicao() ?? 'Não vinculado',
            'ownerDocument' => $vinculo?->pessoa?->documentos->first()?->valor_apresentacao ?? 'Não informado',
            'propertyCode' => $vinculo?->imovel?->codigo ?? 'Sem vínculo fixo',
            'relationship' => $relationshipLabel,
            'updated' => $veiculo->updated_at->format('d/m/Y').' às '.$veiculo->updated_at->format('H:i'),
            'lprStatus' => 'nao_sincronizado',
            'lastReading' => 'Nenhuma leitura registrada',
            'alert' => match (true) {
                $veiculo->status === 'bloqueado' => 'Veículo bloqueado para novas autorizações',
                $vinculo === null => 'Vínculo aguardando conferência da administração',
                default => null,
            },
        ];
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

    public function render(): View
    {
        return view('livewire.vehicle-management', [
            'filteredVehicles' => $this->filteredVehicles(),
            'selectedVehicle' => $this->selectedVehicle(),
            'vehicleCounts' => $this->vehicleCounts(),
            'totalVehicles' => Veiculo::query()->count(),
        ])->layout('components.layouts.app', [
            'title' => 'Veículos',
            'heading' => $this->mode === 'form' ? ($this->editingVehicleId ? 'Editar veículo' : 'Cadastrar veículo') : ($this->mode === 'detail' ? 'Detalhe do veículo' : 'Veículos'),
            'headingDescription' => 'Placas, características, proprietários e vínculos do Condomínio Santa Rita',
        ]);
    }
}

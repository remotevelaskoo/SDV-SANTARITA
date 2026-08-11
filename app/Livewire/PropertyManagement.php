<?php

namespace App\Livewire;

use App\Models\Bloco;
use App\Models\Condominio;
use App\Models\EnderecoImovel;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\Vinculo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PropertyManagement extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todos';

    public ?string $selectedPropertyId = null;

    public ?string $editingPropertyId = null;

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

    public bool $zipCodeLookupFailed = false;

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['todos', 'ativo', 'implantacao', 'inativo', 'bloqueado'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function openProperty(string $id): void
    {
        if (Imovel::query()->whereKey($id)->exists()) {
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

    public function editProperty(string $id): void
    {
        $imovel = Imovel::query()->with(['bloco', 'condominio'])->find($id);

        if (! $imovel) {
            return;
        }

        $endereco = $imovel->enderecoVigente();

        $this->editingPropertyId = $id;
        $this->organization = $imovel->condominio->nome;
        $this->block = $imovel->bloco !== null ? str_replace('Bloco ', '', $imovel->bloco->nome) : '';
        $this->unit = $imovel->unidade;
        $this->code = $imovel->codigo;
        $this->zipCode = $endereco?->zip_code ?? '';
        $this->street = $endereco?->address ?? '';
        $this->number = $endereco?->address_number ?? '';
        $this->complement = $endereco?->address_complement ?? '';
        $this->district = $endereco?->district ?? '';
        $this->city = $endereco?->city ?? 'Taubaté';
        $this->state = $endereco?->state ?? 'SP';
        $this->propertyStatus = $imovel->status;
        $this->notes = '';
        $this->mode = 'form';
        $this->feedback = null;
    }

    public function lookupZipCode(): void
    {
        $this->zipCodeLookupFailed = false;
        $digits = preg_replace('/\D/', '', $this->zipCode);

        if (strlen((string) $digits) !== 8) {
            return;
        }

        try {
            $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$digits}/json/");
        } catch (\Throwable) {
            $this->zipCodeLookupFailed = true;

            return;
        }

        if (! $response->successful() || $response->json('erro')) {
            $this->zipCodeLookupFailed = true;

            return;
        }

        $this->street = $response->json('logradouro') ?: $this->street;
        $this->district = $response->json('bairro') ?: $this->district;
        $this->city = $response->json('localidade') ?: $this->city;
        $this->state = $response->json('uf') ?: $this->state;
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

        $code = strtoupper($this->code);

        $duplicate = Imovel::query()
            ->where('codigo', $code)
            ->when($this->editingPropertyId, fn ($query) => $query->where('id', '!=', $this->editingPropertyId))
            ->exists();

        if ($duplicate) {
            $this->addError('code', 'Já existe um imóvel com este código.');

            return;
        }

        $condominio = Condominio::query()->firstOrCreate(
            ['nome' => $this->organization],
            ['codigo' => Str::upper(Str::slug($this->organization, '')), 'status' => 'ativo']
        );

        $blocoId = null;

        if (trim($this->block) !== '') {
            $letra = strtoupper(trim($this->block));
            $bloco = Bloco::query()->firstOrCreate(
                ['condominio_id' => $condominio->id, 'nome' => "Bloco {$letra}"],
                ['codigo' => $letra, 'status' => 'ativo']
            );
            $blocoId = $bloco->id;
        }

        if ($this->editingPropertyId) {
            $imovel = Imovel::query()->findOrFail($this->editingPropertyId);
            $imovel->update([
                'condominio_id' => $condominio->id,
                'bloco_id' => $blocoId,
                'codigo' => $code,
                'unidade' => $this->unit,
                'status' => $this->propertyStatus,
                'versao' => $imovel->versao + 1,
            ]);
        } else {
            $imovel = Imovel::query()->create([
                'condominio_id' => $condominio->id,
                'bloco_id' => $blocoId,
                'codigo' => $code,
                'unidade' => $this->unit,
                'tipo' => 'apartamento',
                'status' => $this->propertyStatus,
                'versao' => 1,
            ]);
        }

        $enderecoVigente = $imovel->enderecoVigente();
        $enderecoDados = [
            'zip_code' => $this->zipCode,
            'address' => $this->street,
            'address_number' => $this->number,
            'address_complement' => $this->complement !== '' ? $this->complement : null,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
        ];

        if ($enderecoVigente) {
            $enderecoVigente->update($enderecoDados);
        } else {
            EnderecoImovel::query()->create([...$enderecoDados, 'imovel_id' => $imovel->id, 'started_at' => now()]);
        }

        $this->selectedPropertyId = $imovel->id;
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

        $imovel = Imovel::query()->find($this->selectedPropertyId);

        if (! $imovel) {
            return;
        }

        $newStatus = $imovel->status === 'bloqueado' ? 'ativo' : 'bloqueado';
        $imovel->update(['status' => $newStatus, 'versao' => $imovel->versao + 1]);

        $this->feedback = [
            'variant' => $newStatus === 'bloqueado' ? 'warning' : 'success',
            'title' => $newStatus === 'bloqueado' ? 'Imóvel bloqueado' : 'Imóvel reativado',
            'message' => 'A situação estrutural foi alterada. Os vínculos individuais continuam preservados para análise própria.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function filteredProperties(): array
    {
        $search = mb_strtolower(trim($this->search));

        $query = Imovel::query()->with(['bloco', 'condominio']);

        if ($this->statusFilter !== 'todos') {
            $query->where('status', $this->statusFilter);
        }

        $imoveis = $query->orderBy('codigo')->get();

        if ($search !== '') {
            $imoveis = $imoveis->filter(function (Imovel $imovel) use ($search): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $imovel->codigo,
                    $imovel->bloco?->nome,
                    $imovel->unidade,
                    $this->addressLine($imovel),
                    $imovel->responsavelPrincipal()?->nomeExibicao(),
                ])));

                return str_contains($haystack, $search);
            });
        }

        return $imoveis->map(fn (Imovel $imovel) => $this->toArray($imovel))->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function selectedProperty(): ?array
    {
        if ($this->selectedPropertyId === null) {
            return null;
        }

        $imovel = Imovel::query()->with(['bloco', 'condominio'])->find($this->selectedPropertyId);

        return $imovel ? $this->toArray($imovel) : null;
    }

    /** @return array{active: int, implementation: int, blocked: int, occupants: int} */
    public function propertyCounts(): array
    {
        return [
            'active' => Imovel::query()->where('status', 'ativo')->count(),
            'implementation' => Imovel::query()->where('status', 'implantacao')->count(),
            'blocked' => Imovel::query()->where('status', 'bloqueado')->count(),
            'occupants' => Vinculo::query()->whereNull('ended_at')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function toArray(Imovel $imovel): array
    {
        $vinculosAtivos = $imovel->vinculos()->whereNull('ended_at')->with('pessoa.documentos')->get();
        $responsavel = $imovel->responsavelPrincipal();
        $veiculoVinculos = $imovel->veiculoVinculos()->whereNull('ended_at')->with(['veiculo', 'pessoa'])->get();

        return [
            'id' => $imovel->id,
            'code' => $imovel->codigo,
            'block' => $imovel->bloco !== null ? str_replace('Bloco ', '', $imovel->bloco->nome) : '',
            'unit' => $imovel->unidade,
            'address' => $this->addressLine($imovel),
            'status' => $imovel->status,
            'responsible' => $responsavel?->nomeExibicao() ?? 'Não definido',
            'occupants' => $vinculosAtivos->count(),
            'vehicles' => $veiculoVinculos->count(),
            'updated' => $imovel->updated_at->format('d/m/Y').' às '.$imovel->updated_at->format('H:i'),
            'alert' => $this->alertFor($imovel, $responsavel),
            'links' => $vinculosAtivos->map(fn (Vinculo $vinculo) => [
                'name' => $vinculo->pessoa->nomeExibicao(),
                'document' => $vinculo->pessoa->documentos->first()?->valor_apresentacao ?? '—',
                'nature' => $this->naturezaLabel($vinculo->tipo),
                'role' => $this->papelLabel($vinculo->papel),
                'responsibility' => $vinculo->responsabilidades()->where('tipo', 'responsavel_principal')->whereNull('ended_at')->exists()
                    ? 'Responsável principal'
                    : 'Sem responsabilidade administrativa',
                'validity' => $vinculo->ended_at !== null ? 'Até '.$vinculo->ended_at->format('d/m/Y') : 'Prazo indeterminado',
                'status' => $vinculo->status,
            ])->values()->all(),
            'vehicleList' => $veiculoVinculos->map(fn ($veiculoVinculo) => [
                'plate' => $veiculoVinculo->veiculo->plate_display,
                'model' => $veiculoVinculo->veiculo->model,
                'color' => $veiculoVinculo->veiculo->color,
                'owner' => $veiculoVinculo->pessoa?->nomeExibicao() ?? '—',
                'link' => 'Pessoa e imóvel',
                'status' => $veiculoVinculo->veiculo->status,
            ])->values()->all(),
        ];
    }

    private function addressLine(Imovel $imovel): string
    {
        $endereco = $imovel->enderecoVigente();

        if (! $endereco) {
            return 'Endereço não informado';
        }

        $linha = "{$endereco->address}, {$endereco->address_number}";

        if ($endereco->address_complement) {
            $linha .= " · {$endereco->address_complement}";
        }

        return "{$linha} · {$endereco->district}";
    }

    private function alertFor(Imovel $imovel, ?Pessoa $responsavel): ?string
    {
        if ($imovel->status === 'bloqueado') {
            return 'Imóvel bloqueado para novas autorizações';
        }

        if ($responsavel === null) {
            return 'Responsável principal ainda não definido';
        }

        return null;
    }

    private function naturezaLabel(string $tipo): string
    {
        return match ($tipo) {
            'proprietario' => 'Proprietário',
            'inquilino' => 'Inquilino',
            'morador' => 'Morador',
            default => ucfirst($tipo),
        };
    }

    private function papelLabel(?string $papel): string
    {
        return match ($papel) {
            'titular' => 'Titular',
            'conjuge' => 'Cônjuge',
            'dependente' => 'Dependente',
            default => $papel !== null ? ucfirst($papel) : '—',
        };
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
        $this->zipCodeLookupFailed = false;
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
            'propertyCounts' => $this->propertyCounts(),
            'totalProperties' => Imovel::query()->count(),
        ])->layout('components.layouts.app', [
            'title' => 'Imóveis',
            'heading' => $this->mode === 'form' ? ($this->editingPropertyId ? 'Editar imóvel' : 'Cadastrar imóvel') : ($this->mode === 'detail' ? 'Detalhe do imóvel' : 'Imóveis'),
            'headingDescription' => 'Estrutura, endereço, ocupação e vínculos do Condomínio Santa Rita',
        ]);
    }
}

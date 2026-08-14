<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Models\EmpresaDocumento;
use App\Models\EmpresaPrestador;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CompanyManagement extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todas';

    public ?string $selectedCompanyId = null;

    public ?string $editingCompanyId = null;

    public string $cnpj = '';

    public string $name = '';

    public string $tradeName = '';

    public string $category = 'manutencao';

    public string $phone = '';

    public string $email = '';

    public string $companyStatus = 'ativo';

    public string $notes = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['todas', 'ativo', 'inativo'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function openCompany(string $id): void
    {
        if (Empresa::query()->whereKey($id)->exists()) {
            $this->selectedCompanyId = $id;
            $this->mode = 'detail';
            $this->feedback = null;
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->selectedCompanyId = null;
        $this->editingCompanyId = null;
    }

    public function createCompany(): void
    {
        $this->resetForm();
        $this->mode = 'form';
    }

    public function editCompany(string $id): void
    {
        $empresa = Empresa::query()->find($id);

        if (! $empresa) {
            return;
        }

        $this->editingCompanyId = $id;
        $this->cnpj = $empresa->cnpj;
        $this->name = $empresa->razao_social;
        $this->tradeName = $empresa->nome_fantasia ?? '';
        $this->category = $empresa->categoria;
        $this->phone = $empresa->telefone ?? '';
        $this->email = $empresa->email ?? '';
        $this->companyStatus = $empresa->status;
        $this->notes = '';
        $this->mode = 'form';
        $this->feedback = null;
    }

    public function saveDraft(): void
    {
        abort_unless($this->canManage(), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'cnpj' => ['required', 'string'],
        ], ['required' => 'Preencha este campo para salvar o rascunho.']);

        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Rascunho preservado',
            'message' => 'A empresa permanece incompleta. Nenhuma autorização de prestador foi ativada.',
        ];
    }

    public function saveCompany(): void
    {
        abort_unless($this->canManage(), 403);

        $this->validateCompany();

        $duplicate = Empresa::query()
            ->where('cnpj', $this->cnpj)
            ->when($this->editingCompanyId, fn ($query) => $query->where('id', '!=', $this->editingCompanyId))
            ->exists();

        if ($duplicate) {
            $this->addError('cnpj', 'Já existe uma empresa cadastrada com este CNPJ.');

            return;
        }

        $dados = [
            'cnpj' => $this->cnpj,
            'razao_social' => $this->name,
            'nome_fantasia' => $this->tradeName !== '' ? $this->tradeName : null,
            'categoria' => $this->category,
            'telefone' => $this->phone,
            'email' => $this->email,
            'status' => $this->companyStatus,
        ];

        if ($this->editingCompanyId) {
            $empresa = Empresa::query()->findOrFail($this->editingCompanyId);
            $empresa->update([...$dados, 'versao' => $empresa->versao + 1]);
        } else {
            $empresa = Empresa::query()->create([...$dados, 'versao' => 1]);
        }

        $this->selectedCompanyId = $empresa->id;
        $this->mode = 'detail';
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Empresa salva',
            'message' => 'Os dados da empresa foram registrados. Prestadores, documentos e autorizações permanecem separados.',
        ];
    }

    public function toggleCompanyStatus(): void
    {
        abort_unless($this->canManage(), 403);

        if (! $this->selectedCompanyId) {
            return;
        }

        $empresa = Empresa::query()->find($this->selectedCompanyId);

        if (! $empresa) {
            return;
        }

        $newStatus = $empresa->status === 'inativo' ? 'ativo' : 'inativo';
        $empresa->update(['status' => $newStatus, 'versao' => $empresa->versao + 1]);

        $this->feedback = [
            'variant' => $newStatus === 'inativo' ? 'warning' : 'success',
            'title' => $newStatus === 'inativo' ? 'Empresa inativada' : 'Empresa reativada',
            'message' => 'A situação da empresa mudou. Vínculos, documentos e histórico de prestadores continuam preservados.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function filteredCompanies(): array
    {
        $search = mb_strtolower(trim($this->search));

        $query = Empresa::query();

        if ($this->statusFilter !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        $empresas = $query->orderBy('razao_social')->get();

        if ($search !== '') {
            $empresas = $empresas->filter(fn (Empresa $empresa) => str_contains(
                mb_strtolower(implode(' ', array_filter([$empresa->razao_social, $empresa->nome_fantasia, $empresa->cnpj]))),
                $search
            ));
        }

        return $empresas->map(fn (Empresa $empresa) => $this->toArray($empresa))->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function selectedCompany(): ?array
    {
        if ($this->selectedCompanyId === null) {
            return null;
        }

        $empresa = Empresa::query()->find($this->selectedCompanyId);

        return $empresa ? $this->toArray($empresa) : null;
    }

    /** @return array{active: int, inactive: int, providers: int, expiringDocuments: int} */
    public function companyCounts(): array
    {
        return [
            'active' => Empresa::query()->where('status', 'ativo')->count(),
            'inactive' => Empresa::query()->where('status', 'inativo')->count(),
            'providers' => EmpresaPrestador::query()->count(),
            'expiringDocuments' => EmpresaDocumento::query()->whereIn('status', ['vencendo', 'expirado'])->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function toArray(Empresa $empresa): array
    {
        $prestadores = $empresa->prestadores()->with('pessoa.documentos')->get();
        $documentos = $empresa->documentos()->get();
        $servicos = $empresa->servicos()->get();

        return [
            'id' => $empresa->id,
            'cnpj' => $empresa->cnpj,
            'name' => $empresa->razao_social,
            'tradeName' => $empresa->nome_fantasia ?? $empresa->razao_social,
            'category' => $empresa->categoria,
            'status' => $empresa->status,
            'phone' => $empresa->telefone ?? '—',
            'email' => $empresa->email ?? '—',
            'updated' => $empresa->updated_at->format('d/m/Y').' às '.$empresa->updated_at->format('H:i'),
            'alert' => $this->alertFor($empresa, $documentos),
            'providers' => $prestadores->map(fn (EmpresaPrestador $prestador) => [
                'name' => $prestador->pessoa->nomeExibicao(),
                'document' => $prestador->pessoa->documentos->first()?->valor_apresentacao ?? '—',
                'role' => $prestador->atividade,
                'validity' => $prestador->ended_at !== null ? 'Encerrado em '.$prestador->ended_at->format('d/m/Y') : 'Prazo indeterminado',
                'status' => $prestador->status,
            ])->values()->all(),
            'documents' => $documentos->map(fn ($documento) => [
                'label' => $documento->tipo,
                'state' => $documento->status,
            ])->values()->all(),
            'services' => $servicos->map(fn ($servico) => [
                'label' => $servico->atividade,
                'status' => $servico->status,
            ])->values()->all(),
        ];
    }

    private function alertFor(Empresa $empresa, Collection $documentos): ?string
    {
        if ($empresa->status === 'inativo') {
            return 'Empresa inativa — sem novas autorizações';
        }

        if ($documentos->contains(fn ($documento) => $documento->status === 'vencendo')) {
            return 'Documento a vencer — revise a certidão';
        }

        if ($documentos->contains(fn ($documento) => $documento->status === 'expirado')) {
            return 'Documento expirado — revise a certidão';
        }

        return null;
    }

    private function canManage(): bool
    {
        return Auth::user()?->hasPermission('empresas.gerenciar') ?? false;
    }

    private function resetForm(): void
    {
        $this->editingCompanyId = null;
        $this->cnpj = '';
        $this->name = '';
        $this->tradeName = '';
        $this->category = 'manutencao';
        $this->phone = '';
        $this->email = '';
        $this->companyStatus = 'ativo';
        $this->notes = '';
        $this->feedback = null;
        $this->resetErrorBag();
    }

    private function validateCompany(): void
    {
        $this->validate([
            'cnpj' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:160'],
            'tradeName' => ['nullable', 'string', 'max:160'],
            'category' => ['required', Rule::in(['manutencao', 'limpeza', 'seguranca', 'jardinagem', 'entregas', 'outro'])],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email'],
            'companyStatus' => ['required', Rule::in(['ativo', 'inativo'])],
            'notes' => ['nullable', 'string', 'max:300'],
        ], [
            'required' => 'Preencha este campo para salvar a empresa.',
        ]);
    }

    public function render(): View
    {
        return view('livewire.company-management', [
            'filteredCompanies' => $this->filteredCompanies(),
            'selectedCompany' => $this->selectedCompany(),
            'companyCounts' => $this->companyCounts(),
            'totalCompanies' => Empresa::query()->count(),
        ])->layout('components.layouts.app', [
            'title' => 'Empresas e prestadores',
            'heading' => $this->mode === 'form' ? ($this->editingCompanyId ? 'Editar empresa' : 'Cadastrar empresa') : ($this->mode === 'detail' ? 'Detalhe da empresa' : 'Empresas e prestadores'),
            'headingDescription' => 'Empresas, prestadores vinculados, documentos e autorizações por serviço',
        ]);
    }
}

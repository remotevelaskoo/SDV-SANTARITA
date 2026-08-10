<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CompanyManagement extends Component
{
    public string $mode = 'list';

    public string $search = '';

    public string $statusFilter = 'todas';

    public ?int $selectedCompanyId = null;

    public ?int $editingCompanyId = null;

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

    /** @var list<array<string, mixed>> */
    public array $companies = [
        [
            'id' => 1, 'cnpj' => '12.345.678/0001-90', 'name' => 'Manutenção Predial Vale Ltda', 'tradeName' => 'ValeManutenção', 'category' => 'manutencao', 'status' => 'ativo', 'phone' => '(24) 3344-5566', 'email' => 'contato@valemanutencao.com.br', 'updated' => '10/08/2026 às 15:10', 'alert' => null,
            'providers' => [
                ['name' => 'Sérgio Aparecido Luz', 'document' => '***.447.601-**', 'role' => 'Técnico eletricista', 'validity' => 'Até 31/12/2026', 'status' => 'ativo'],
                ['name' => 'Luciana Ferraz', 'document' => '***.218.904-**', 'role' => 'Técnica hidráulica', 'validity' => 'Até 31/12/2026', 'status' => 'ativo'],
            ],
            'documents' => [
                ['label' => 'Contrato de prestação de serviço', 'state' => 'validado'],
                ['label' => 'Comprovante de CNPJ', 'state' => 'validado'],
                ['label' => 'Certidão negativa de débitos', 'state' => 'enviado'],
            ],
            'services' => [
                ['label' => 'Manutenção elétrica', 'status' => 'autorizado'],
                ['label' => 'Manutenção hidráulica', 'status' => 'autorizado'],
            ],
        ],
        [
            'id' => 2, 'cnpj' => '23.456.789/0001-11', 'name' => 'Limpa Fácil Serviços de Limpeza Ltda', 'tradeName' => 'Limpa Fácil', 'category' => 'limpeza', 'status' => 'ativo', 'phone' => '(24) 3322-1100', 'email' => 'financeiro@limpafacil.com.br', 'updated' => '09/08/2026 às 11:40', 'alert' => 'Certidão negativa vence em 12 dias',
            'providers' => [
                ['name' => 'Marta Oliveira Santos', 'document' => '***.552.310-**', 'role' => 'Auxiliar de limpeza', 'validity' => 'Até 30/09/2026', 'status' => 'ativo'],
            ],
            'documents' => [
                ['label' => 'Contrato de prestação de serviço', 'state' => 'validado'],
                ['label' => 'Certidão negativa de débitos', 'state' => 'vencendo'],
            ],
            'services' => [
                ['label' => 'Limpeza de áreas comuns', 'status' => 'autorizado'],
            ],
        ],
        [
            'id' => 3, 'cnpj' => '34.567.890/0001-22', 'name' => 'Vale Segurança Patrimonial Ltda', 'tradeName' => 'Vale Segurança', 'category' => 'seguranca', 'status' => 'ativo', 'phone' => '(24) 3355-7788', 'email' => 'operacoes@valeseguranca.com.br', 'updated' => '08/08/2026 às 09:15', 'alert' => null,
            'providers' => [
                ['name' => 'Eduardo Nogueira', 'document' => '***.760.115-**', 'role' => 'Vigilante', 'validity' => 'Prazo indeterminado', 'status' => 'ativo'],
                ['name' => 'Rafael Domingues', 'document' => '***.004.120-**', 'role' => 'Supervisor', 'validity' => 'Prazo indeterminado', 'status' => 'ativo'],
            ],
            'documents' => [
                ['label' => 'Contrato de prestação de serviço', 'state' => 'validado'],
                ['label' => 'Alvará de funcionamento', 'state' => 'validado'],
                ['label' => 'Comprovante de CNPJ', 'state' => 'validado'],
            ],
            'services' => [
                ['label' => 'Rondas patrimoniais', 'status' => 'autorizado'],
                ['label' => 'Monitoramento de câmeras', 'status' => 'autorizado'],
            ],
        ],
        [
            'id' => 4, 'cnpj' => '45.678.901/0001-33', 'name' => 'Jardim Verde Paisagismo Ltda', 'tradeName' => 'Jardim Verde', 'category' => 'jardinagem', 'status' => 'inativo', 'phone' => '(24) 3300-9911', 'email' => 'contato@jardimverde.com.br', 'updated' => '20/06/2026 às 16:00', 'alert' => 'Empresa inativa — sem novas autorizações',
            'providers' => [
                ['name' => 'Bianca Moretti', 'document' => '***.615.338-**', 'role' => 'Jardineira', 'validity' => 'Encerrado em 20/06/2026', 'status' => 'encerrado'],
            ],
            'documents' => [
                ['label' => 'Contrato de prestação de serviço', 'state' => 'expirado'],
            ],
            'services' => [
                ['label' => 'Paisagismo e jardinagem', 'status' => 'suspenso'],
            ],
        ],
    ];

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['todas', 'ativo', 'inativo'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function openCompany(int $id): void
    {
        if ($this->findCompany($id)) {
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

    public function editCompany(int $id): void
    {
        $company = $this->findCompany($id);

        if (! $company) {
            return;
        }

        $this->editingCompanyId = $id;
        $this->cnpj = $company['cnpj'];
        $this->name = $company['name'];
        $this->tradeName = $company['tradeName'];
        $this->category = $company['category'];
        $this->phone = $company['phone'];
        $this->email = $company['email'];
        $this->companyStatus = $company['status'];
        $this->notes = '';
        $this->mode = 'form';
        $this->feedback = null;
    }

    public function saveDraft(): void
    {
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
        $this->validateCompany();

        foreach ($this->companies as $company) {
            if ($company['cnpj'] === $this->cnpj && $company['id'] !== $this->editingCompanyId) {
                $this->addError('cnpj', 'Já existe uma empresa cadastrada com este CNPJ.');

                return;
            }
        }

        if ($this->editingCompanyId) {
            foreach ($this->companies as $index => $company) {
                if ($company['id'] === $this->editingCompanyId) {
                    $this->companies[$index] = array_merge($company, [
                        'cnpj' => $this->cnpj, 'name' => $this->name, 'tradeName' => $this->tradeName, 'category' => $this->category, 'phone' => $this->phone, 'email' => $this->email, 'status' => $this->companyStatus, 'updated' => '10/08/2026 às 19:00',
                    ]);
                }
            }
            $id = $this->editingCompanyId;
        } else {
            $id = max(array_column($this->companies, 'id')) + 1;
            $this->companies[] = [
                'id' => $id, 'cnpj' => $this->cnpj, 'name' => $this->name, 'tradeName' => $this->tradeName, 'category' => $this->category, 'status' => $this->companyStatus, 'phone' => $this->phone, 'email' => $this->email, 'updated' => '10/08/2026 às 19:00', 'alert' => null, 'providers' => [], 'documents' => [], 'services' => [],
            ];
        }

        $this->selectedCompanyId = $id;
        $this->mode = 'detail';
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Empresa salva',
            'message' => 'Os dados da empresa foram registrados. Prestadores, documentos e autorizações permanecem separados.',
        ];
    }

    public function toggleCompanyStatus(): void
    {
        if (! $this->selectedCompanyId) {
            return;
        }

        foreach ($this->companies as $index => $company) {
            if ($company['id'] === $this->selectedCompanyId) {
                $newStatus = $company['status'] === 'inativo' ? 'ativo' : 'inativo';
                $this->companies[$index]['status'] = $newStatus;
                $this->companies[$index]['alert'] = $newStatus === 'inativo' ? 'Empresa inativa — sem novas autorizações' : null;
                $this->feedback = [
                    'variant' => $newStatus === 'inativo' ? 'warning' : 'success',
                    'title' => $newStatus === 'inativo' ? 'Empresa inativada' : 'Empresa reativada',
                    'message' => 'A situação da empresa mudou. Vínculos, documentos e histórico de prestadores continuam preservados.',
                ];
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function filteredCompanies(): array
    {
        $search = mb_strtolower(trim($this->search));

        return array_values(array_filter($this->companies, function (array $company) use ($search): bool {
            $matchesStatus = $this->statusFilter === 'todas' || $company['status'] === $this->statusFilter;
            $matchesSearch = $search === '' || str_contains(mb_strtolower(implode(' ', [$company['name'], $company['tradeName'], $company['cnpj']])), $search);

            return $matchesStatus && $matchesSearch;
        }));
    }

    /** @return array<string, mixed>|null */
    public function selectedCompany(): ?array
    {
        return $this->selectedCompanyId ? $this->findCompany($this->selectedCompanyId) : null;
    }

    /** @return array<string, mixed>|null */
    private function findCompany(int $id): ?array
    {
        foreach ($this->companies as $company) {
            if ($company['id'] === $id) {
                return $company;
            }
        }

        return null;
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
        ])->layout('components.layouts.app', [
            'title' => 'Empresas e prestadores',
            'heading' => $this->mode === 'form' ? ($this->editingCompanyId ? 'Editar empresa' : 'Cadastrar empresa') : ($this->mode === 'detail' ? 'Detalhe da empresa' : 'Empresas e prestadores'),
            'headingDescription' => 'Empresas, prestadores vinculados, documentos e autorizações por serviço',
        ]);
    }
}

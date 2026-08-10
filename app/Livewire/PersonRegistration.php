<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PersonRegistration extends Component
{
    /** Documento usado apenas para demonstrar a checagem de duplicidade (EX-001). */
    private const DEMO_EXISTING_DOCUMENT = '111.111.111-11';

    public string $accessType = 'resident';

    public int $currentStep = 1;

    public int $maxStepReached = 1;

    // Etapa 1 — Dados pessoais
    public string $fullName = '';

    public string $socialName = '';

    public string $document = '';

    public string $rg = '';

    public string $rgIssuer = '';

    public string $birthDate = '';

    public string $maritalStatus = '';

    public string $nationality = 'Brasileira';

    public string $profession = '';

    public string $company = '';

    public string $email = '';

    public string $phone = '';

    public bool $duplicateFound = false;

    // Etapa 2 — Documentos e fotos
    public string $documentType = 'rg';

    public string $documentState = 'nao_enviado';

    // Etapa 3 — Endereço e contato
    /** @var array{property: string, address: string} */
    public array $linkedProperty = [
        'property' => 'Bloco B — Apto 304',
        'address' => 'Rua das Palmeiras, 245 — Santa Rita, Volta Redonda/RJ',
    ];

    // Etapa 4 — Informações de acesso
    public string $property = 'Bloco B — Apto 304';

    public string $responsible = '';

    public string $nature = 'morador';

    public string $role = 'titular';

    public string $startDate = '';

    public string $endDate = '';

    public bool $indefiniteTerm = true;

    /** @var list<string> */
    public array $areas = ['comum'];

    public string $schedule = 'integral';

    // Etapa 5 — Observações
    public string $notes = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public ?string $protocol = null;

    /** @return list<array{number: int, label: string, description: string}> */
    public function steps(): array
    {
        return [
            ['label' => 'Dados pessoais', 'description' => 'Identificação da pessoa'],
            ['label' => 'Documentos e fotos', 'description' => 'Comprovação e foto facial'],
            ['label' => 'Endereço e contato', 'description' => 'Vínculo com o imóvel'],
            ['label' => 'Informações de acesso', 'description' => 'Vigência e permissões'],
            ['label' => 'Observações', 'description' => 'Informações operacionais'],
        ];
    }

    public function updatedAccessType(string $value): void
    {
        $this->nature = match ($value) {
            'resident' => 'morador',
            'tenant' => 'inquilino',
            default => 'outro',
        };
        $this->indefiniteTerm = ! in_array($value, ['tenant', 'visitor', 'tourist'], true);
    }

    public function checkDocument(): void
    {
        $this->duplicateFound = $this->document !== '' && $this->document === self::DEMO_EXISTING_DOCUMENT;
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= $this->maxStepReached) {
            $this->currentStep = $step;
        }
    }

    public function nextStep(): void
    {
        $rules = $this->rulesForStep($this->currentStep);

        if ($rules !== []) {
            $this->validate($rules, [], $this->attributeNames());
        }

        $this->currentStep = min(5, $this->currentStep + 1);
        $this->maxStepReached = max($this->maxStepReached, $this->currentStep);
    }

    public function previousStep(): void
    {
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function cancel(): void
    {
        $this->reset();
        $this->feedback = [
            'variant' => 'info',
            'title' => 'Cadastro cancelado',
            'message' => 'Nenhuma alteração foi salva.',
        ];
    }

    public function saveDraft(): void
    {
        $this->validate([
            'fullName' => ['required', 'string', 'min:3'],
            'document' => ['required', 'string'],
        ], [], $this->attributeNames());

        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Rascunho salvo',
            'message' => 'O cadastro foi preservado como incompleto. Vínculo, autorização e sincronização não foram ativados.',
        ];
        $this->protocol = $this->generateProtocol();
    }

    public function activate(): void
    {
        $rules = array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(3),
            $this->rulesForStep(4),
        );

        $this->validate($rules, [], $this->attributeNames());

        if ($this->duplicateFound) {
            $this->feedback = [
                'variant' => 'danger',
                'title' => 'Não é possível ativar',
                'message' => 'Já existe uma pessoa com este documento. Selecione o cadastro existente para criar um vínculo.',
            ];

            return;
        }

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Pessoa e vínculo salvos',
            'message' => 'A sincronização facial está pendente. Resultado demonstrativo — nenhum dado real foi gravado.',
        ];
        $this->protocol = $this->generateProtocol();
    }

    /** @return array<string, array<int, mixed>> */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'fullName' => ['required', 'string', 'min:3'],
                'document' => ['required', 'string'],
                'birthDate' => ['required', 'date'],
                'email' => ['nullable', 'email'],
                'phone' => ['required', 'string', 'min:8'],
            ],
            3 => [
                'property' => ['required', 'string'],
            ],
            4 => [
                'nature' => ['required', Rule::in(['proprietario', 'morador', 'inquilino', 'outro'])],
                'role' => ['required', Rule::in(['titular', 'conjuge', 'filho', 'dependente', 'outro'])],
                'startDate' => ['required', 'date'],
                'endDate' => $this->indefiniteTerm ? ['nullable'] : ['required', 'date', 'after:startDate'],
                'responsible' => in_array($this->accessType, ['visitor', 'tourist'], true) ? ['required', 'string'] : ['nullable'],
                'company' => $this->accessType === 'provider' ? ['required', 'string'] : ['nullable'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        return [
            'fullName' => 'nome completo',
            'document' => 'documento',
            'birthDate' => 'data de nascimento',
            'phone' => 'telefone',
            'property' => 'imóvel',
            'nature' => 'natureza',
            'role' => 'papel',
            'startDate' => 'data de início',
            'endDate' => 'data de término',
            'responsible' => 'responsável',
            'company' => 'empresa',
        ];
    }

    private function generateProtocol(): string
    {
        return 'SRP-'.now()->format('Ymd').'-'.random_int(100000, 999999);
    }

    public function render(): View
    {
        return view('livewire.person-registration')
            ->layout('components.layouts.app', [
                'title' => 'Cadastro de pessoa',
                'heading' => 'Cadastro de pessoa',
                'headingDescription' => 'Protótipo demonstrativo — nenhum dado é persistido',
            ]);
    }
}

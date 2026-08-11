<?php

namespace App\Livewire;

use App\Support\DestinationDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PublicPreRegistration extends Component
{
    public bool $started = false;

    public bool $submitted = false;

    public int $step = 1;

    public string $name = '';

    public string $cpf = '';

    public string $birthDate = '';

    public string $phone = '';

    public string $email = '';

    public string $accessType = 'turista';

    public string $zipCode = '';

    public bool $zipCodeLookupFailed = false;

    public string $address = '';

    public string $addressNumber = '';

    public string $addressComplement = '';

    public string $district = '';

    public string $city = '';

    public string $state = '';

    public string $destinationProperty = 'Bloco B — Apto 304';

    public bool $documentReady = false;

    public bool $selfieReady = false;

    public bool $hasVehicle = false;

    public string $plate = '';

    public string $vehicleModel = '';

    public string $vehicleColor = '';

    public bool $privacyAccepted = false;

    public bool $draftSaved = false;

    public string $protocol = 'PRE-SRA-2026-X7K9M2';

    /** @var list<array{label: string, description: string}> */
    public array $steps = [
        ['label' => 'Dados pessoais', 'description' => 'Identificação e contato'],
        ['label' => 'Endereço', 'description' => 'Endereço informado'],
        ['label' => 'Documento', 'description' => 'Captura e conferência'],
        ['label' => 'Selfie', 'description' => 'Imagem para análise'],
        ['label' => 'Veículo', 'description' => 'Informação opcional'],
        ['label' => 'Confirmação', 'description' => 'Revisão e envio'],
    ];

    public function start(): void
    {
        $this->started = true;
        $this->submitted = false;
        $this->step = 1;
    }

    public function nextStep(): void
    {
        $this->validateCurrentStep();
        $this->draftSaved = false;
        $this->step = min(6, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->draftSaved = false;
        $this->step = max(1, $this->step - 1);
    }

    public function editStep(int $step): void
    {
        if ($step >= 1 && $step < $this->step) {
            $this->step = $step;
        }
    }

    public function saveDraft(): void
    {
        $this->draftSaved = true;
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

        $this->address = $response->json('logradouro') ?: $this->address;
        $this->district = $response->json('bairro') ?: $this->district;
        $this->city = $response->json('localidade') ?: $this->city;
        $this->state = $response->json('uf') ?: $this->state;
    }

    public function destinationResponsible(): string
    {
        return DestinationDirectory::responsibleFor($this->destinationProperty) ?? 'Não definido';
    }

    /** @return list<string> */
    public function destinationOptions(): array
    {
        return DestinationDirectory::options();
    }

    public function markDocumentReady(): void
    {
        $this->documentReady = true;
        $this->resetErrorBag('documentReady');
    }

    public function markSelfieReady(): void
    {
        $this->selfieReady = true;
        $this->resetErrorBag('selfieReady');
    }

    public function submit(): void
    {
        $this->step = 6;
        $this->validateCurrentStep();
        $this->submitted = true;
        $this->draftSaved = false;
    }

    public function restart(): void
    {
        $this->reset();
    }

    private function validateCurrentStep(): void
    {
        $rules = match ($this->step) {
            1 => [
                'name' => ['required', 'string', 'min:3', 'max:120'],
                'cpf' => ['required', 'string', 'min:11', 'max:14'],
                'birthDate' => ['required', 'date', 'before:today'],
                'phone' => ['required', 'string', 'min:10', 'max:20'],
                'email' => ['required', 'email', 'max:120'],
                'accessType' => ['required', Rule::in(['visitante', 'turista', 'prestador'])],
            ],
            2 => [
                'zipCode' => ['required', 'string', 'min:8', 'max:9'],
                'address' => ['required', 'string', 'max:160'],
                'addressNumber' => ['required', 'string', 'max:20'],
                'district' => ['required', 'string', 'max:80'],
                'city' => ['required', 'string', 'max:80'],
                'state' => ['required', 'string', 'size:2'],
                'destinationProperty' => $this->accessType === 'turista'
                    ? []
                    : ['required', Rule::in($this->destinationOptions())],
            ],
            3 => ['documentReady' => ['accepted']],
            4 => ['selfieReady' => ['accepted']],
            5 => $this->hasVehicle ? [
                'plate' => ['required', 'string', 'min:7', 'max:8'],
                'vehicleModel' => ['required', 'string', 'max:100'],
                'vehicleColor' => ['required', 'string', 'max:40'],
            ] : [],
            6 => ['privacyAccepted' => ['accepted']],
            default => [],
        };

        if ($rules === []) {
            return;
        }

        $this->validate($rules, [
            'required' => 'Preencha este campo para continuar.',
            'email' => 'Informe um e-mail válido.',
            'before' => 'Informe uma data de nascimento válida.',
            'accepted' => 'Confirme esta informação para continuar.',
            'size' => 'Use a sigla do estado com duas letras.',
        ]);
    }

    public function render(): View
    {
        return view('livewire.public-pre-registration')
            ->layout('components.layouts.guest', ['title' => 'Pré-cadastro de visitante']);
    }
}

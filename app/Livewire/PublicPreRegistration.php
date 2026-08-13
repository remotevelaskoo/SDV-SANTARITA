<?php

namespace App\Livewire;

use App\Models\PreRegistration;
use App\Services\PrivateFileService;
use App\Support\DestinationDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class PublicPreRegistration extends Component
{
    use WithFileUploads;

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

    public mixed $documentFile = null;

    public bool $selfieReady = false;

    public mixed $selfieFile = null;

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

    public function updatedDocumentFile(): void
    {
        $this->validateOnly('documentFile', [
            'documentFile' => $this->protectedImageRules(),
        ], $this->fileValidationMessages());

        $this->documentReady = true;
        $this->resetErrorBag('documentReady');
    }

    public function updatedSelfieFile(): void
    {
        $this->validateOnly('selfieFile', [
            'selfieFile' => $this->protectedImageRules(),
        ], $this->fileValidationMessages());

        $this->selfieReady = true;
        $this->resetErrorBag('selfieReady');
    }

    public function removeDocumentFile(): void
    {
        $this->documentFile = null;
        $this->documentReady = false;
        $this->resetErrorBag(['documentFile', 'documentReady']);
    }

    public function removeSelfieFile(): void
    {
        $this->selfieFile = null;
        $this->selfieReady = false;
        $this->resetErrorBag(['selfieFile', 'selfieReady']);
    }

    public function submit(PrivateFileService $privateFiles): void
    {
        $this->step = 6;
        $this->validate($this->submissionRules(), array_merge(
            $this->validationMessages(),
            $this->fileValidationMessages(),
        ));

        $addressLine = "{$this->address}, {$this->addressNumber}";

        if ($this->addressComplement !== '') {
            $addressLine .= " · {$this->addressComplement}";
        }

        $addressLine .= " · {$this->district} · {$this->city}/{$this->state}";

        $isTourist = $this->accessType === 'turista';

        try {
            $preRegistration = DB::transaction(function () use ($addressLine, $isTourist, $privateFiles): PreRegistration {
                $preRegistration = PreRegistration::query()->create([
                    'protocol' => $this->generateProtocol(),
                    'name' => $this->name,
                    'document' => $this->cpf,
                    'birth_date' => $this->birthDate,
                    'phone' => $this->phone,
                    'email' => $this->email,
                    'access_type' => $this->accessType,
                    'address_informed' => $addressLine,
                    'destination_property' => $isTourist ? null : $this->destinationProperty,
                    'destination_label' => $isTourist ? 'Praia do Santa Rita' : $this->destinationProperty,
                    'responsible_name' => $isTourist ? null : $this->destinationResponsible(),
                    // O formulário público ainda não coleta o período pretendido da
                    // visita — usamos uma janela padrão de 24h a partir do envio até
                    // essa decisão de produto ser tomada (registrado em docs/013).
                    'period_start' => now(),
                    'period_end' => now()->addDay(),
                    'vehicle_plate' => $this->hasVehicle ? $this->plate : null,
                    'vehicle_model' => $this->hasVehicle ? $this->vehicleModel : null,
                    'vehicle_color' => $this->hasVehicle ? $this->vehicleColor : null,
                    'document_status' => 'Arquivo protegido disponível para conferência',
                    'selfie_status' => 'Arquivo protegido disponível para conferência',
                    'status' => 'aguardando',
                    'submitted_at' => now(),
                ]);

                $privateFiles->storePreRegistrationFiles($preRegistration, [
                    'documento' => $this->documentFile,
                    'selfie' => $this->selfieFile,
                ]);

                return $preRegistration;
            });
        } catch (\Throwable) {
            $this->addError('submission', 'Não foi possível proteger os arquivos agora. Seus dados continuam nesta tela para uma nova tentativa.');

            return;
        }

        $this->protocol = $preRegistration->protocol;
        $this->submitted = true;
        $this->draftSaved = false;
    }

    private function generateProtocol(): string
    {
        return 'PRE-SRA-'.strtoupper(Str::random(6));
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
            3 => ['documentFile' => $this->protectedImageRules()],
            4 => ['selfieFile' => $this->protectedImageRules()],
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

        $this->validate($rules, array_merge($this->validationMessages(), $this->fileValidationMessages()));
    }

    /** @return array<string, array<int, mixed>> */
    private function submissionRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'cpf' => ['required', 'string', 'min:11', 'max:14'],
            'birthDate' => ['required', 'date', 'before:today'],
            'phone' => ['required', 'string', 'min:10', 'max:20'],
            'email' => ['required', 'email', 'max:120'],
            'accessType' => ['required', Rule::in(['visitante', 'turista', 'prestador'])],
            'zipCode' => ['required', 'string', 'min:8', 'max:9'],
            'address' => ['required', 'string', 'max:160'],
            'addressNumber' => ['required', 'string', 'max:20'],
            'district' => ['required', 'string', 'max:80'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'size:2'],
            'destinationProperty' => $this->accessType === 'turista' ? [] : ['required', Rule::in($this->destinationOptions())],
            'documentFile' => $this->protectedImageRules(),
            'selfieFile' => $this->protectedImageRules(),
            'plate' => $this->hasVehicle ? ['required', 'string', 'min:7', 'max:8'] : [],
            'vehicleModel' => $this->hasVehicle ? ['required', 'string', 'max:100'] : [],
            'vehicleColor' => $this->hasVehicle ? ['required', 'string', 'max:40'] : [],
            'privacyAccepted' => ['accepted'],
        ];
    }

    /** @return array<int, string> */
    private function protectedImageRules(): array
    {
        return ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'];
    }

    /** @return array<string, string> */
    private function validationMessages(): array
    {
        return [
            'required' => 'Preencha este campo para continuar.',
            'email' => 'Informe um e-mail válido.',
            'before' => 'Informe uma data de nascimento válida.',
            'accepted' => 'Confirme esta informação para continuar.',
            'size' => 'Use a sigla do estado com duas letras.',
        ];
    }

    /** @return array<string, string> */
    private function fileValidationMessages(): array
    {
        return [
            'documentFile.required' => 'Envie uma foto do documento para continuar.',
            'selfieFile.required' => 'Envie uma selfie para continuar.',
            'image' => 'O arquivo precisa ser uma imagem válida.',
            'mimes' => 'Use uma imagem JPG, PNG ou WebP.',
            'max' => 'A imagem deve ter no máximo 8 MB.',
        ];
    }

    public function render(): View
    {
        return view('livewire.public-pre-registration')
            ->layout('components.layouts.guest', ['title' => 'Pré-cadastro de visitante']);
    }
}

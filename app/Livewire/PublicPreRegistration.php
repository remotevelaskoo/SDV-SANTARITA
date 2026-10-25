<?php

namespace App\Livewire;

use App\Documentos\Dominio\DiagnosticoLeitura;
use App\Documentos\Dominio\FalhaLeituraDocumento;
use App\Models\PreRegistration;
use App\Services\DocumentOcrClient;
use App\Services\PrivateFileService;
use App\Support\DestinationDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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

    public string $documentType = 'cnh';

    public bool $documentReady = false;

    public mixed $documentFile = null;

    public bool $documentBackReady = false;

    public mixed $documentBackFile = null;

    public string $ocrStatus = 'idle';

    public string $ocrMessage = '';

    /**
     * Motivo objetivo da falha ou da leitura parcial (AJ-006), sem dado lido.
     *
     * @var array{categoria: string, titulo: string, motivos: list<string>, recapturar: bool}|null
     */
    public ?array $ocrDiagnostico = null;

    /** @var array<string, string> */
    public array $ocrFields = [];

    /** @var array<string, float> */
    public array $ocrConfidences = [];

    public string $ocrEngine = '';

    public string $ocrModelVersion = '';

    public string $extractedDocumentNumber = '';

    public bool $portraitDetected = false;

    public string $portraitSource = '';

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
        if ($this->step === 3 && ! $this->documentStepEnabled()) {
            $this->step = 4;
        }
    }

    public function previousStep(): void
    {
        $this->draftSaved = false;
        $this->step = max(1, $this->step - 1);
        if ($this->step === 3 && ! $this->documentStepEnabled()) {
            $this->step = 2;
        }
    }

    /** Etapa 3 (documento e leitura assistida) desligada por configuração. */
    public function documentStepEnabled(): bool
    {
        return (bool) config('documentos.pre_cadastro.etapa_documento');
    }

    /**
     * Etapas exibidas ao visitante, numeradas na ordem em que aparecem.
     *
     * @return list<array{label: string, description: string, state: string, step: int}>
     */
    public function visibleSteps(): array
    {
        $visible = [];
        foreach ($this->steps as $index => $definition) {
            $number = $index + 1;
            if ($number === 3 && ! $this->documentStepEnabled()) {
                continue;
            }
            $visible[] = $definition + [
                'step' => $number,
                'state' => $number < $this->step ? 'complete' : ($number === $this->step ? 'current' : 'future'),
            ];
        }

        return $visible;
    }

    public function editStep(int $step): void
    {
        if ($step === 3 && ! $this->documentStepEnabled()) {
            return;
        }

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
        $this->analyzeDocument();
    }

    public function updatedDocumentBackFile(): void
    {
        if ($this->documentBackFile === null) {
            $this->documentBackReady = false;

            return;
        }

        $this->validateOnly('documentBackFile', [
            'documentBackFile' => $this->optionalProtectedImageRules(),
        ], $this->fileValidationMessages());

        $this->documentBackReady = true;
        $this->resetErrorBag('documentBackFile');
        $this->analyzeDocument();
    }

    public function updatedDocumentType(): void
    {
        if ($this->documentFile !== null) {
            $this->analyzeDocument();
        }
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
        $this->resetOcrState();
        $this->resetErrorBag(['documentFile', 'documentReady']);
    }

    public function removeDocumentBackFile(): void
    {
        $this->documentBackFile = null;
        $this->documentBackReady = false;
        $this->resetErrorBag('documentBackFile');

        if ($this->documentFile !== null) {
            $this->analyzeDocument();
        }
    }

    public function analyzeDocument(): void
    {
        if ($this->documentFile === null) {
            $this->resetOcrState();

            return;
        }

        $ocr = app(DocumentOcrClient::class);
        if (! $ocr->enabled()) {
            $this->ocrStatus = 'unavailable';
            $this->ocrMessage = 'A leitura automática não está ativa neste ambiente. Você pode continuar preenchendo os dados manualmente.';
            $this->ocrDiagnostico = DiagnosticoLeitura::servico('desligado')->paraTela();

            return;
        }

        $rateLimitKey = 'document-ocr:'.hash('sha256', (string) request()->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->ocrStatus = 'failed';
            $this->ocrMessage = 'Foram feitas muitas tentativas de leitura. Aguarde um minuto e continue manualmente ou tente novamente.';
            $this->ocrDiagnostico = DiagnosticoLeitura::servico('tentativas_excedidas')->paraTela();

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $this->ocrStatus = 'processing';
        $this->ocrMessage = 'Lendo o documento em ambiente interno…';
        $this->ocrFields = [];
        $this->ocrConfidences = [];
        $this->ocrDiagnostico = null;
        $this->portraitDetected = false;
        $this->portraitSource = '';

        try {
            $result = $ocr->extract($this->documentType, $this->documentFile, $this->documentBackFile);
        } catch (FalhaLeituraDocumento $falha) {
            $this->registrarFalhaOcr(DiagnosticoLeitura::daFalha($falha));

            return;
        } catch (\Throwable) {
            $this->registrarFalhaOcr(DiagnosticoLeitura::servico('erro_interno'));

            return;
        }

        foreach ($result['fields'] as $field => $suggestion) {
            if (! is_array($suggestion) || ! is_string($suggestion['value'] ?? null) || $suggestion['value'] === '') {
                continue;
            }

            $this->ocrFields[$field] = $suggestion['value'];
            if (is_numeric($suggestion['confidence'] ?? null)) {
                $this->ocrConfidences[$field] = (float) $suggestion['confidence'];
            }
        }

        $diagnostico = DiagnosticoLeitura::daResposta($result);
        Log::info('leitura_documento', ['origem' => 'pre_cadastro', ...$diagnostico->paraLog()]);

        if ($this->ocrFields === []) {
            $this->ocrStatus = 'failed';
            $this->ocrMessage = 'Não conseguimos identificar os dados com segurança. Veja abaixo o que ajustar, envie outra foto ou continue manualmente.';
            $this->ocrDiagnostico = $diagnostico->paraTela();

            return;
        }

        // Sugestões só preenchem; o solicitante confere e corrige antes do envio.
        $this->name = $this->ocrFields['name'] ?? $this->name;
        $this->cpf = $this->ocrFields['cpf'] ?? $this->cpf;
        $this->birthDate = $this->ocrFields['birth_date'] ?? $this->birthDate;
        $this->extractedDocumentNumber = $this->ocrFields['document_number'] ?? '';
        $this->ocrEngine = (string) ($result['engine'] ?? '');
        $this->ocrModelVersion = (string) ($result['model_version'] ?? '');
        $portrait = is_array($result['portrait'] ?? null) ? $result['portrait'] : [];
        $this->portraitDetected = ($portrait['detected'] ?? false) === true;
        $this->portraitSource = is_string($portrait['source'] ?? null) ? $portrait['source'] : '';

        $this->ocrStatus = 'completed';
        $this->ocrMessage = 'Encontramos sugestões. Volte aos dados pessoais para conferir e corrigir antes do envio.';
        $this->ocrDiagnostico = $diagnostico->motivos() !== [] ? $diagnostico->paraTela() : null;
    }

    private function registrarFalhaOcr(DiagnosticoLeitura $diagnostico): void
    {
        Log::info('leitura_documento', ['origem' => 'pre_cadastro', ...$diagnostico->paraLog()]);

        $this->ocrStatus = 'failed';
        $this->ocrMessage = 'A leitura automática não foi concluída. Seus dados e sua imagem foram mantidos.';
        $this->ocrDiagnostico = $diagnostico->paraTela();
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
        $portraitBytes = $this->preparePortraitCrop();

        try {
            $preRegistration = DB::transaction(function () use ($addressLine, $isTourist, $privateFiles): PreRegistration {
                $preRegistration = PreRegistration::query()->create([
                    'protocol' => $this->generateProtocol(),
                    'name' => $this->name,
                    'document' => $this->cpf,
                    'document_type' => $this->documentStepEnabled() ? $this->documentType : null,
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
                    'document_status' => $this->documentStepEnabled()
                        ? 'Arquivo protegido disponível para conferência'
                        : 'Documento não solicitado no pré-cadastro; conferir na portaria',
                    'selfie_status' => 'Arquivo protegido disponível para conferência',
                    'status' => 'aguardando',
                    'submitted_at' => now(),
                ]);

                $uploads = ['selfie' => $this->selfieFile];

                if ($this->documentStepEnabled() && $this->documentFile !== null) {
                    $uploads['documento'] = $this->documentFile;
                }

                if ($this->documentStepEnabled() && $this->documentBackFile !== null) {
                    $uploads['documento_verso'] = $this->documentBackFile;
                }

                $privateFiles->storePreRegistrationFiles($preRegistration, $uploads);

                if ($this->ocrStatus === 'completed') {
                    $preRegistration->documentExtractions()->create([
                        'document_type' => $this->documentType,
                        'engine' => $this->ocrEngine,
                        'model_version' => $this->ocrModelVersion,
                        'status' => 'confirmed',
                        'extracted_fields' => $this->ocrFields,
                        'field_confidences' => $this->ocrConfidences,
                        'confirmed_fields' => [
                            'name' => $this->name,
                            'cpf' => $this->cpf,
                            'birth_date' => $this->birthDate,
                            'document_number' => $this->extractedDocumentNumber,
                            'document_portrait_detected' => $this->portraitDetected,
                        ],
                        'processed_at' => now(),
                        'confirmed_at' => now(),
                    ]);
                }

                return $preRegistration;
            });
        } catch (\Throwable) {
            $this->addError('submission', 'Não foi possível proteger os arquivos agora. Seus dados continuam nesta tela para uma nova tentativa.');

            return;
        }

        if ($portraitBytes !== null) {
            try {
                $privateFiles->storeGeneratedPortrait($preRegistration, $portraitBytes);
            } catch (\Throwable) {
                // O recorte é um auxílio opcional. Documento e selfie originais
                // permanecem disponíveis para conferência humana.
            }
        }

        $this->protocol = $preRegistration->protocol;
        $this->submitted = true;
        $this->draftSaved = false;
    }

    private function generateProtocol(): string
    {
        return 'PRE-SRA-'.strtoupper(Str::random(6));
    }

    private function resetOcrState(): void
    {
        $this->ocrStatus = 'idle';
        $this->ocrMessage = '';
        $this->ocrDiagnostico = null;
        $this->ocrFields = [];
        $this->ocrConfidences = [];
        $this->ocrEngine = '';
        $this->ocrModelVersion = '';
        $this->extractedDocumentNumber = '';
        $this->portraitDetected = false;
        $this->portraitSource = '';
    }

    private function preparePortraitCrop(): ?string
    {
        if (! $this->portraitDetected || $this->documentFile === null) {
            return null;
        }

        try {
            $ocr = app(DocumentOcrClient::class);
            $result = $ocr->extract(
                $this->documentType,
                $this->documentFile,
                $this->documentBackFile,
                includePortrait: true,
            );

            return $ocr->portraitBytes($result);
        } catch (\Throwable) {
            return null;
        }
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
            3 => [
                'documentType' => ['required', Rule::in(['rg_cin', 'cnh'])],
                'documentFile' => $this->protectedImageRules(),
                'documentBackFile' => $this->optionalProtectedImageRules(),
            ],
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
            'documentType' => $this->documentStepEnabled() ? ['required', Rule::in(['rg_cin', 'cnh'])] : [],
            'documentFile' => $this->documentStepEnabled() ? $this->protectedImageRules() : [],
            'documentBackFile' => $this->documentStepEnabled() ? $this->optionalProtectedImageRules() : [],
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

    /** @return array<int, string> */
    private function optionalProtectedImageRules(): array
    {
        return ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'];
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
            'documentType.required' => 'Escolha o tipo de documento.',
            'documentType.in' => 'Escolha RG/CIN ou CNH.',
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

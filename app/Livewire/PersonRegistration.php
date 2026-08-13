<?php

namespace App\Livewire;

use App\Models\Arquivo;
use App\Models\Imovel;
use App\Models\Implantacao;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\Vinculo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class PersonRegistration extends Component
{
    use WithFileUploads;

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

    public ?TemporaryUploadedFile $photo = null;

    // Etapa 4 — Informações de acesso
    public string $property = '';

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
        $normalizado = preg_replace('/\D/', '', $this->document) ?? '';

        $this->duplicateFound = $normalizado !== '' && PessoaDocumento::query()
            ->where('tipo', 'cpf')
            ->where('valor_normalizado', $normalizado)
            ->exists();
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

        $this->checkDocument();

        if ($this->duplicateFound) {
            $this->feedback = [
                'variant' => 'danger',
                'title' => 'Não é possível ativar',
                'message' => 'Já existe uma pessoa com este documento. Selecione o cadastro existente para criar um vínculo.',
            ];

            return;
        }

        $imovel = Imovel::query()->where('codigo', $this->property)->first();

        if (! $imovel) {
            $this->addError('property', 'Selecione um imóvel válido.');

            return;
        }

        $pessoa = Pessoa::query()->create([
            'nome' => $this->fullName,
            'nome_social' => $this->socialName !== '' ? $this->socialName : null,
            'data_nascimento' => $this->birthDate,
            'status' => 'ativo',
            'versao' => 1,
        ]);

        PessoaDocumento::query()->create([
            'pessoa_id' => $pessoa->id,
            'tipo' => 'cpf',
            'pais' => 'BR',
            'valor_normalizado' => preg_replace('/\D/', '', $this->document) ?? '',
            'valor_apresentacao' => $this->document,
            'status' => 'ativo',
            'started_at' => now(),
        ]);

        PessoaContato::query()->create([
            'pessoa_id' => $pessoa->id,
            'tipo' => 'telefone',
            'valor' => $this->phone,
            'principal' => true,
            'verificado' => false,
            'started_at' => now(),
        ]);

        if ($this->email !== '') {
            PessoaContato::query()->create([
                'pessoa_id' => $pessoa->id,
                'tipo' => 'email',
                'valor' => $this->email,
                'principal' => false,
                'verificado' => false,
                'started_at' => now(),
            ]);
        }

        Vinculo::query()->create([
            'pessoa_id' => $pessoa->id,
            'imovel_id' => $imovel->id,
            'tipo' => $this->nature,
            'papel' => $this->role,
            'status' => 'ativo',
            'origem' => 'cadastro_manual',
            'started_at' => $this->startDate,
            'ended_at' => $this->indefiniteTerm ? null : $this->endDate,
            'versao' => 1,
        ]);

        if ($this->photo) {
            $this->attachPhoto($pessoa);
        }

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Pessoa e vínculo salvos',
            'message' => 'O cadastro e o vínculo com o imóvel foram registrados. A sincronização facial ainda depende de equipamento real.',
        ];
        $this->protocol = $this->generateProtocol();
    }

    /** @return array{property: string, address: string} */
    public function linkedPropertyInfo(): array
    {
        $imovel = $this->property !== '' ? Imovel::query()->where('codigo', $this->property)->first() : null;

        if (! $imovel) {
            return [
                'property' => 'Nenhum imóvel selecionado ainda',
                'address' => 'Selecione o imóvel na etapa "Informações de acesso".',
            ];
        }

        $endereco = $imovel->enderecoVigente();

        return [
            'property' => $imovel->label(),
            'address' => $endereco
                ? "{$endereco->address}, {$endereco->address_number} — {$endereco->district}, {$endereco->city}/{$endereco->state}"
                : 'Endereço não cadastrado para este imóvel.',
        ];
    }

    /** @return Collection<int, Imovel> */
    public function imoveis(): Collection
    {
        return Imovel::query()->orderBy('codigo')->get();
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
            2 => [
                'photo' => ['nullable', 'image', 'max:5120'],
            ],
            4 => [
                'property' => ['required', 'string'],
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

    private function attachPhoto(Pessoa $pessoa): void
    {
        // Chave opaca (ADR-006 §12.1): não leva o id da pessoa, nome ou
        // documento — só implantação, categoria e um uuid novo.
        $extensao = $this->photo->getClientOriginalExtension();
        $caminho = sprintf(
            '%s/pessoas-fotos/%s/%s.%s',
            Implantacao::current()->id,
            now()->format('Y/m'),
            Str::uuid7(),
            $extensao,
        );

        $checksum = hash_file('sha256', $this->photo->getRealPath());
        $nomeOriginal = $this->photo->getClientOriginalName();
        $mime = $this->photo->getMimeType();
        $tamanho = $this->photo->getSize();

        $this->photo->storeAs('', $caminho, ['disk' => 'local']);

        Arquivo::query()->create([
            'fileable_type' => Pessoa::class,
            'fileable_id' => $pessoa->id,
            'categoria' => 'foto_pessoa',
            'disco' => 'local',
            'caminho' => $caminho,
            'nome_original' => $nomeOriginal,
            'mime' => $mime,
            'tamanho' => $tamanho,
            'checksum' => $checksum,
            'created_by' => Auth::id(),
        ]);
    }

    public function render(): View
    {
        return view('livewire.person-registration', [
            'linkedProperty' => $this->linkedPropertyInfo(),
            'imoveis' => $this->imoveis(),
        ])->layout('components.layouts.app', [
            'title' => 'Cadastro de pessoa',
            'heading' => 'Cadastro de pessoa',
            'headingDescription' => 'Pessoa, documento e vínculo com o imóvel',
        ]);
    }
}

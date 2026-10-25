<?php

namespace App\Livewire;

use App\Acesso\Aplicacao\AutorizacoesAcesso;
use App\Acesso\Aplicacao\EnvioFacial;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Models\AutorizacaoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\AuditService;
use App\Services\PrivateFileService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class PersonRegistration extends Component
{
    use WithFileUploads;

    /** Foto real da pessoa (AJ-006): armazenamento privado, exibida na Validação de Entrada. */
    public ?TemporaryUploadedFile $photo = null;

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

    // AJ-006, fatia 6 — ações finais e envio para a facial.

    /** Pessoa salva neste atendimento (ou carregada para nova visita). */
    #[Locked]
    public ?string $pessoaId = null;

    /** Vínculo criado neste atendimento. */
    #[Locked]
    public ?string $vinculoId = null;

    /** Visita de turista criada neste atendimento. */
    #[Locked]
    public ?string $autorizacaoId = null;

    /** @var array<string, mixed> valores do formulário no último salvamento */
    #[Locked]
    public array $valoresSalvos = [];

    /** Cadastro existente com o mesmo CPF (para registrar nova visita do turista). */
    #[Locked]
    public ?string $pessoaExistenteId = null;

    public string $terminalId = '';

    public bool $terminalConfirmado = false;

    /** @var array{variant: string, title: string, message: string, avisos: list<string>}|null */
    public ?array $resultadoEnvio = null;

    public function mount(EnvioFacial $envio): void
    {
        $usuario = Auth::user();
        if (! $usuario instanceof User || ! $usuario->hasPermission('facial.enviar')) {
            return;
        }

        // Contexto do posto: com um único terminal possível, ele já vem escolhido (ainda exige confirmação).
        $terminais = $envio->terminaisPara($usuario);
        $doPosto = $terminais->where('do_posto', true);
        $unico = $doPosto->count() === 1 ? $doPosto->first() : ($terminais->count() === 1 ? $terminais->first() : null);
        $this->terminalId = $unico['id'] ?? '';
    }

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

        $existente = $normalizado === '' ? null : PessoaDocumento::query()
            ->where('tipo', 'cpf')
            ->where('valor_normalizado', $normalizado)
            ->value('pessoa_id');

        // O próprio cadastro salvo neste atendimento não conta como duplicidade.
        $this->duplicateFound = $existente !== null && $existente !== $this->pessoaId;
        $this->pessoaExistenteId = $this->duplicateFound ? $existente : null;
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

    /**
     * Encerra só o atendimento atual: descarta o rascunho e o que não foi
     * salvo. Pessoa, vínculo, autorização e histórico já salvos ficam.
     */
    public function cancel(): void
    {
        $salvo = $this->pessoaId !== null;
        $this->reset();
        $this->mount(app(EnvioFacial::class));
        $this->feedback = $salvo
            ? [
                'variant' => 'info',
                'title' => 'Atendimento encerrado',
                'message' => 'O cadastro salvo foi mantido. Apenas o que não tinha sido salvo foi descartado; nada foi apagado.',
            ]
            : [
                'variant' => 'info',
                'title' => 'Cadastro cancelado',
                'message' => 'Nenhuma alteração foi salva.',
            ];
    }

    /** Volta o formulário aos valores do último salvamento. */
    public function discardChanges(): void
    {
        if ($this->pessoaId === null) {
            return;
        }

        foreach ($this->valoresSalvos as $campo => $valor) {
            $this->{$campo} = $valor;
        }
        $this->photo = null;
        $this->resetErrorBag();
        $this->resultadoEnvio = null;
    }

    public function hasPendingChanges(): bool
    {
        return $this->pessoaId !== null && ($this->valoresFormulario() != $this->valoresSalvos || $this->photo !== null);
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

    /** Salvar cadastro (sem envio para a facial). */
    public function activate(): void
    {
        $this->resultadoEnvio = null;
        $this->salvar();
    }

    /** Salvar e enviar para a facial: salva primeiro; uma falha no envio não desfaz o salvamento. */
    public function saveAndSend(EnvioFacial $envio): void
    {
        $this->autorizarEnvio();
        $this->resultadoEnvio = null;
        if (! $this->terminalEscolhido($envio)) {
            return;
        }

        if (! $this->salvar()) {
            $this->resultadoEnvio = [
                'variant' => 'warning',
                'title' => 'Envio para a facial não realizado',
                'message' => 'O cadastro não foi salvo, então nada foi enviado. Corrija os campos indicados e tente de novo.',
                'avisos' => [],
            ];

            return;
        }

        $this->enviarAoTerminal($envio);
    }

    /** Enviar para a facial uma pessoa já salva, sem salvar nada. */
    public function send(EnvioFacial $envio): void
    {
        $this->autorizarEnvio();
        $this->resultadoEnvio = null;

        if ($this->pessoaId === null) {
            $this->addError('envio', 'Salve o cadastro antes de enviar para a facial.');

            return;
        }
        if ($this->hasPendingChanges()) {
            $this->addError('envio', 'Há alterações não salvas. Salve ou descarte as alterações antes de enviar para a facial.');

            return;
        }
        if (! $this->terminalEscolhido($envio)) {
            return;
        }

        $this->enviarAoTerminal($envio);
    }

    /**
     * Turista que já tem cadastro: usa a pessoa existente e cria uma nova
     * autorização de visita no próximo salvamento. Os dados dela não mudam.
     */
    public function useExistingForVisit(): void
    {
        if ($this->accessType !== 'tourist' || $this->pessoaExistenteId === null) {
            return;
        }

        $pessoa = Pessoa::query()->with('contatos')->findOrFail($this->pessoaExistenteId);
        $this->pessoaId = $pessoa->id;
        $this->vinculoId = null;
        $this->autorizacaoId = null;
        $this->fullName = $pessoa->nome;
        $this->socialName = (string) $pessoa->nome_social;
        $this->birthDate = $pessoa->data_nascimento?->format('Y-m-d') ?? '';
        $this->phone = (string) $pessoa->contatos->firstWhere('tipo', 'telefone')?->valor;
        $this->email = (string) $pessoa->contatos->firstWhere('tipo', 'email')?->valor;
        $this->duplicateFound = false;
        $this->pessoaExistenteId = null;
        $this->valoresSalvos = $this->valoresFormulario();
        $this->feedback = [
            'variant' => 'info',
            'title' => 'Cadastro existente selecionado',
            'message' => 'Os dados da pessoa foram mantidos. Informe o período da visita e salve: uma nova autorização será criada.',
        ];
    }

    /**
     * Grava a pessoa: cria no primeiro salvamento e atualiza nos seguintes.
     * Turista ganha uma autorização de visita própria.
     */
    private function salvar(): bool
    {
        $novaVisita = $this->accessType === 'tourist' && $this->pessoaId !== null && $this->autorizacaoId === null;

        $rules = array_merge(
            $this->rulesForStep(1),
            $novaVisita ? [] : $this->rulesForStep(3),
            $this->rulesForStep(4),
        );
        $rules['photo'] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'];
        $this->validate($rules, [], $this->attributeNames());

        if ($this->pessoaId !== null) {
            return $this->atualizar($novaVisita);
        }

        $this->checkDocument();

        if ($this->duplicateFound) {
            $this->feedback = [
                'variant' => 'danger',
                'title' => 'Não é possível ativar',
                'message' => $this->accessType === 'tourist'
                    ? 'Já existe uma pessoa com este documento. Use o cadastro existente para registrar uma nova visita.'
                    : 'Já existe uma pessoa com este documento. Selecione o cadastro existente para criar um vínculo.',
            ];

            return false;
        }

        $imovel = Imovel::query()->where('codigo', $this->property)->first();

        if (! $imovel) {
            $this->addError('property', 'Selecione um imóvel válido.');

            return false;
        }

        try {
            $pessoa = DB::transaction(function () use ($imovel): Pessoa {
                $pessoa = Pessoa::query()->create([
                    'nome' => $this->fullName,
                    'nome_social' => $this->socialName !== '' ? $this->socialName : null,
                    'data_nascimento' => $this->birthDate,
                    'status' => 'ativo',
                    'versao' => 1,
                    'created_by' => Auth::id(),
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

                $vinculo = Vinculo::query()->create([
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
                $this->vinculoId = $vinculo->id;

                if ($this->accessType === 'tourist') {
                    $this->autorizacaoId = $this->registrarVisita($pessoa, $imovel)->id;
                }

                app(AuditService::class)->record(
                    action: 'pessoa_cadastrada',
                    module: 'pessoas',
                    entityType: 'pessoas',
                    entityId: $pessoa->id,
                    metadata: ['vinculo_id' => $vinculo->id, 'tipo_acesso' => $this->accessType, 'autorizacao_id' => $this->autorizacaoId],
                );

                return $pessoa;
            });
        } catch (RegraIntegracaoViolada $e) {
            $this->vinculoId = $this->autorizacaoId = null;
            $this->feedback = ['variant' => 'danger', 'title' => 'Cadastro não salvo', 'message' => $e->getMessage()];

            return false;
        }

        $this->pessoaId = $pessoa->id;
        $this->guardarFoto($pessoa);
        $this->valoresSalvos = $this->valoresFormulario();

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Pessoa e vínculo salvos',
            'message' => $this->accessType === 'tourist'
                ? 'O cadastro e o vínculo com o imóvel foram registrados. '.$this->descricaoVisita()
                : 'O cadastro e o vínculo com o imóvel foram registrados.',
        ];
        $this->protocol = $this->generateProtocol();

        return true;
    }

    /** Salva alterações de uma pessoa já salva (ou a nova visita de quem já tinha cadastro). */
    private function atualizar(bool $novaVisita): bool
    {
        $pessoa = Pessoa::query()->findOrFail($this->pessoaId);
        $salvos = $this->valoresSalvos;

        if (($salvos['document'] ?? null) !== $this->document) {
            $this->addError('document', 'O CPF de um cadastro salvo não é alterado aqui. Descarte a alteração.');

            return false;
        }
        if (! $novaVisita && ($salvos['property'] ?? null) !== $this->property) {
            $this->addError('property', 'O imóvel de um cadastro salvo não é alterado aqui. Descarte a alteração.');

            return false;
        }
        if ($this->autorizacaoId !== null && array_diff_assoc(
            array_intersect_key($this->valoresFormulario(), array_flip(['startDate', 'endDate', 'responsible'])),
            array_intersect_key($salvos, array_flip(['startDate', 'endDate', 'responsible'])),
        ) !== []) {
            $this->addError('startDate', 'O período de uma visita já salva não é alterado aqui. Descarte a alteração.');

            return false;
        }

        $imovel = $novaVisita ? Imovel::query()->where('codigo', $this->property)->first() : null;
        if ($novaVisita && $imovel === null) {
            $this->addError('property', 'Selecione um imóvel válido.');

            return false;
        }

        $alterados = array_keys(array_diff_assoc(
            array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $this->valoresFormulario()),
            array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $salvos),
        ));
        if ($this->photo !== null) {
            $alterados[] = 'photo';
        }

        try {
            DB::transaction(function () use ($pessoa, $alterados, $novaVisita, $imovel): void {
                if (array_intersect($alterados, ['fullName', 'socialName', 'birthDate']) !== []) {
                    $pessoa->forceFill([
                        'nome' => $this->fullName,
                        'nome_social' => $this->socialName !== '' ? $this->socialName : null,
                        'data_nascimento' => $this->birthDate,
                        'versao' => $pessoa->versao + 1,
                        'updated_by' => Auth::id(),
                    ])->save();
                }
                if (in_array('phone', $alterados, true)) {
                    $this->trocarContato($pessoa, 'telefone', $this->phone, true);
                }
                if (in_array('email', $alterados, true)) {
                    $this->trocarContato($pessoa, 'email', $this->email, false);
                }
                if ($this->vinculoId !== null && array_intersect($alterados, ['nature', 'role', 'startDate', 'endDate', 'indefiniteTerm']) !== []) {
                    $vinculo = Vinculo::query()->findOrFail($this->vinculoId);
                    $vinculo->forceFill([
                        'tipo' => $this->nature,
                        'papel' => $this->role,
                        'started_at' => $this->startDate,
                        'ended_at' => $this->indefiniteTerm ? null : $this->endDate,
                        'versao' => $vinculo->versao + 1,
                    ])->save();
                }
                if ($novaVisita) {
                    $this->autorizacaoId = $this->registrarVisita($pessoa, $imovel)->id;
                }

                app(AuditService::class)->record(
                    action: $novaVisita ? 'turista_nova_visita' : 'pessoa_atualizada',
                    module: 'pessoas',
                    entityType: 'pessoas',
                    entityId: $pessoa->id,
                    // Só os nomes dos campos: nada de valor pessoal na auditoria.
                    metadata: ['campos' => array_values($alterados), 'autorizacao_id' => $this->autorizacaoId],
                );
            });
        } catch (RegraIntegracaoViolada $e) {
            if ($novaVisita) {
                $this->autorizacaoId = null;
            }
            $this->feedback = ['variant' => 'danger', 'title' => 'Alterações não salvas', 'message' => $e->getMessage()];

            return false;
        }

        $this->guardarFoto($pessoa);
        $this->valoresSalvos = $this->valoresFormulario();
        $this->feedback = $novaVisita
            ? ['variant' => 'success', 'title' => 'Nova visita registrada', 'message' => 'O cadastro existente foi mantido. '.$this->descricaoVisita()]
            : ($alterados === []
                ? ['variant' => 'info', 'title' => 'Nenhuma alteração pendente', 'message' => 'O cadastro já estava salvo com estes dados.']
                : ['variant' => 'success', 'title' => 'Alterações salvas', 'message' => 'Os dados da pessoa foram atualizados.']);

        return true;
    }

    private function registrarVisita(Pessoa $pessoa, Imovel $imovel): AutorizacaoAcesso
    {
        $fuso = (string) config('app.timezone');
        $usuario = Auth::user();

        return app(AutorizacoesAcesso::class)->registrarVisitaTurista(
            $pessoa,
            $imovel,
            $this->responsible,
            $this->startDate !== '' ? CarbonImmutable::parse($this->startDate, $fuso)->startOfDay() : null,
            $this->endDate !== '' ? CarbonImmutable::parse($this->endDate, $fuso)->endOfDay() : null,
            $usuario instanceof User ? $usuario : null,
        );
    }

    private function descricaoVisita(): string
    {
        $autorizacao = $this->autorizacaoId !== null ? AutorizacaoAcesso::query()->find($this->autorizacaoId) : null;

        return $autorizacao === null ? '' : sprintf(
            'Visita autorizada de %s até %s (no máximo %dh).',
            $autorizacao->inicio->format('d/m/Y H:i'),
            $autorizacao->fim->format('d/m/Y H:i'),
            AutorizacoesAcesso::validadeTuristaHoras(),
        );
    }

    private function trocarContato(Pessoa $pessoa, string $tipo, string $valor, bool $principal): void
    {
        // O contato anterior é encerrado, não apagado.
        PessoaContato::query()->where('pessoa_id', $pessoa->id)->where('tipo', $tipo)->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        if ($valor !== '') {
            PessoaContato::query()->create([
                'pessoa_id' => $pessoa->id,
                'tipo' => $tipo,
                'valor' => $valor,
                'principal' => $principal,
                'verificado' => false,
                'started_at' => now(),
            ]);
        }
    }

    private function guardarFoto(Pessoa $pessoa): void
    {
        if ($this->photo !== null) {
            app(PrivateFileService::class)->storePessoaFoto($pessoa, $this->photo);
            $this->photo->delete();
            $this->photo = null;
        }
    }

    private function enviarAoTerminal(EnvioFacial $envio): void
    {
        $pessoa = Pessoa::query()->findOrFail($this->pessoaId);
        $autorizacao = $this->autorizacaoId !== null ? AutorizacaoAcesso::query()->find($this->autorizacaoId) : null;

        $this->resultadoEnvio = $envio->enviar($pessoa, $this->terminalId, $this->operador(), $autorizacao)->paraTela();
        $this->terminalConfirmado = false;
    }

    private function terminalEscolhido(EnvioFacial $envio): bool
    {
        if ($this->terminalId === '' || $envio->terminaisPara($this->operador())->firstWhere('id', $this->terminalId) === null) {
            $this->addError('terminalId', 'Escolha o terminal facial que vai receber o cadastro.');

            return false;
        }
        if (! $this->terminalConfirmado) {
            $this->addError('terminalConfirmado', 'Confirme o terminal escolhido antes de enviar.');

            return false;
        }

        return true;
    }

    private function autorizarEnvio(): void
    {
        abort_unless($this->operador()->hasPermission('facial.enviar'), 403);
    }

    private function operador(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }

    /** @return array<string, mixed> */
    private function valoresFormulario(): array
    {
        return [
            'accessType' => $this->accessType,
            'fullName' => $this->fullName,
            'socialName' => $this->socialName,
            'document' => $this->document,
            'birthDate' => $this->birthDate,
            'email' => $this->email,
            'phone' => $this->phone,
            'property' => $this->property,
            'responsible' => $this->responsible,
            'nature' => $this->nature,
            'role' => $this->role,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'indefiniteTerm' => $this->indefiniteTerm,
        ];
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

    public function render(): View
    {
        $usuario = Auth::user();
        $podeEnviar = $usuario instanceof User && $usuario->hasPermission('facial.enviar');
        $envio = app(EnvioFacial::class);
        $terminais = $podeEnviar ? $envio->terminaisPara($usuario) : collect();

        return view('livewire.person-registration', [
            'linkedProperty' => $this->linkedPropertyInfo(),
            'imoveis' => $this->imoveis(),
            'podeEnviar' => $podeEnviar,
            'terminais' => $terminais,
            'pendente' => $this->hasPendingChanges(),
            'situacaoFacial' => $this->pessoaId !== null ? $envio->situacao(Pessoa::query()->findOrFail($this->pessoaId)) : collect(),
            'visita' => $this->autorizacaoId !== null ? AutorizacaoAcesso::query()->find($this->autorizacaoId) : null,
        ])->layout('components.layouts.app', [
            'title' => 'Cadastro de pessoa',
            'heading' => 'Cadastro de pessoa',
            'headingDescription' => 'Pessoa, documento e vínculo com o imóvel',
        ]);
    }
}

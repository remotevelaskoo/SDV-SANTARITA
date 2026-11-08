<?php

namespace App\Livewire\Concerns;

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
use App\Services\ValidadorFotoPessoa;
use App\Support\NormalizadorBusca;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Cadastro rápido no atendimento (AJ-010, SDV-OPE-021).
 *
 * Sequência curta dentro da Validação de entrada: Documento → Conferência →
 * Foto → salvar (ou salvar e enviar para a facial). O atendimento em curso
 * (contribuição, ticket, veículo e observações) não é tocado.
 *
 * - CPF existente nunca vira outra pessoa: o operador escolhe reutilizar o
 *   cadastro, sem sobrescrever identidade, contato ou foto em silêncio;
 * - a foto da pessoa é uma captura própria, separada do documento, e só é
 *   gravada (no armazenamento privado) ao salvar;
 * - salvar não cria autorização; o envio à facial segue as regras do AJ-006
 *   e mostra cadastro, foto e terminal como resultados separados;
 * - cada ação confere permissão no servidor; clique duplo não duplica nada.
 */
trait CadastroRapidoAtendimento
{
    public const ETAPAS_CADASTRO = ['documento', 'conferencia', 'foto'];

    /** Tipos com visita de até 24 horas no envio à facial (modelo do AJ-006). */
    public bool $quickRegistrationOpen = false;

    public bool $quickPersonRegistered = false;

    public bool $quickDuplicateFound = false;

    public string $quickEtapa = 'documento';

    public string $quickName = '';

    public string $quickDocument = '';

    public string $quickPhone = '';

    public string $quickAccessType = 'visitante';

    public string $quickResponsible = '';

    public string $quickPropertyCode = '';

    public string $quickNotes = '';

    /** Cadastro existente escolhido explicitamente para este atendimento. */
    #[Locked]
    public ?string $quickPessoaExistenteId = null;

    /** Identifica este cadastro rápido: o mesmo envio nunca é gravado duas vezes. */
    #[Locked]
    public ?string $quickToken = null;

    /** Foto atual da pessoa (captura própria, não é o documento nem o recorte dele). */
    public ?TemporaryUploadedFile $fotoPessoa = null;

    /** Para cadastro reutilizado que já tem foto: trocar exige marcação explícita. */
    public bool $quickSubstituirFoto = false;

    public string $quickTerminalId = '';

    public bool $quickTerminalConfirmado = false;

    /** @var array{cadastro: array<string, string>, foto: array<string, string>, facial: array<string, mixed>|null}|null */
    #[Locked]
    public ?array $quickResultado = null;

    public function openQuickRegistration(): void
    {
        $this->exigirPermissoesCadastro();

        // O que o operador já digitou na busca vira ponto de partida do cadastro.
        $termo = trim($this->busca);
        if ($this->quickName === '' && preg_match('/^[\p{L} \'.-]{3,}$/u', $termo)) {
            $this->quickName = $termo;
        } elseif ($this->quickDocument === '' && strlen(NormalizadorBusca::digitos($termo)) === 11) {
            $this->quickDocument = $termo;
        }

        $this->quickRegistrationOpen = true;
        $this->quickDuplicateFound = false;
        $this->quickResultado = null;
        $this->quickToken = (string) Str::uuid7();
        $this->quickEtapa = $this->podeCapturarDocumento() ? 'documento' : 'conferencia';
        $this->feedback = null;
        $this->protocol = null;
        $this->preSelecionarTerminal();
        $this->resetErrorBag();
    }

    public function cancelQuickRegistration(): void
    {
        $this->quickRegistrationOpen = false;
        $this->quickDuplicateFound = false;
        $this->resetQuickRegistrationFields();
        $this->resetErrorBag();
    }

    /** Navegação entre as etapas; avançar para a foto exige os dados conferidos. */
    public function irParaEtapa(string $etapa): void
    {
        $this->exigirPermissoesCadastro();
        if (! in_array($etapa, self::ETAPAS_CADASTRO, true)) {
            return;
        }
        if ($etapa === 'foto' && ! $this->dadosConferidos()) {
            return;
        }
        $this->quickEtapa = $etapa;
    }

    /**
     * Nome completo ditado no navegador (docs/021 §12). Só preenche o campo:
     * não salva, não fotografa, não autoriza e não envia. A transcrição não
     * vai para log nem auditoria; conta como edição do operador para o OCR.
     */
    public function preencherNomePorVoz(string $texto): void
    {
        $this->exigirPermissoesCadastro();
        if (! $this->quickRegistrationOpen || $this->quickEtapa === 'foto') {
            return;
        }

        $nome = trim((string) preg_replace('/\s+/u', ' ', $texto));
        if ($nome === '') {
            return; // resultado vazio não apaga o nome atual
        }

        $this->quickName = mb_substr($nome, 0, 120);
        $this->marcarEditado('quickName');
        $this->resetErrorBag('quickName');
        $this->validateOnly('quickName', ['quickName' => ['required', 'string', 'min:3', 'max:120']], ['min' => 'Informe um valor válido.']);
    }

    /** Ao trocar o tipo, o responsável só sobrevive para morador (docs/021 §14). */
    public function updatedQuickAccessType(): void
    {
        $this->descartarResponsavelForaDeMorador();
    }

    /** Valor residual ou forjado para outro tipo é ignorado: nada de responsável fictício. */
    private function descartarResponsavelForaDeMorador(): void
    {
        if ($this->quickAccessType !== 'morador') {
            $this->quickResponsible = '';
            $this->resetErrorBag('quickResponsible');
        }
    }

    public function checkQuickDocument(): void
    {
        $normalizado = $this->normalizeDocument($this->quickDocument);
        $existente = $normalizado === '' ? null : PessoaDocumento::query()
            ->where('tipo', 'cpf')
            ->where('valor_normalizado', $normalizado)
            ->value('pessoa_id');

        if ($this->quickPessoaExistenteId !== null && $existente !== $this->quickPessoaExistenteId) {
            // O CPF mudou depois da escolha: a reutilização deixa de valer.
            $this->quickPessoaExistenteId = null;
            $this->quickSubstituirFoto = false;
        }

        $this->quickDuplicateFound = $existente !== null && $existente !== $this->quickPessoaExistenteId;

        if ($this->quickDuplicateFound) {
            $this->addError('quickDocument', 'Esta pessoa já possui cadastro. Use o cadastro existente para evitar duplicidade.');
        } else {
            $this->resetErrorBag('quickDocument');
        }
    }

    /** Reutilização explícita do cadastro com o mesmo CPF (nada é sobrescrito). */
    public function usarCadastroExistente(AuditService $audit): void
    {
        $this->exigirPermissoesCadastro();
        $normalizado = $this->normalizeDocument($this->quickDocument);
        $pessoaId = $normalizado === '' ? null : PessoaDocumento::query()
            ->where('tipo', 'cpf')
            ->where('valor_normalizado', $normalizado)
            ->value('pessoa_id');
        abort_if($pessoaId === null, 404);

        $pessoa = Pessoa::query()->findOrFail($pessoaId);
        $this->quickPessoaExistenteId = $pessoa->id;
        $this->quickName = $pessoa->nome;
        $this->quickDuplicateFound = false;
        $this->camposConferencia = array_values(array_diff($this->camposConferencia, ['quickName', 'quickDocument']));
        $this->resetErrorBag(['quickDocument', 'quickName']);

        $audit->record(
            action: 'cadastro_rapido_reutilizou_pessoa',
            module: 'validacao',
            entityType: 'pessoas',
            entityId: $pessoa->id,
            metadata: ['origem' => 'cpf_existente'],
        );
    }

    /** @return array{nome: string, documento: string, tem_foto: bool}|null */
    public function cadastroExistenteResumo(): ?array
    {
        $normalizado = $this->normalizeDocument($this->quickDocument);
        $pessoaId = $this->quickPessoaExistenteId ?? ($normalizado === '' ? null : PessoaDocumento::query()
            ->where('tipo', 'cpf')->where('valor_normalizado', $normalizado)->value('pessoa_id'));
        $pessoa = $pessoaId !== null ? Pessoa::query()->find($pessoaId) : null;

        return $pessoa === null ? null : [
            'nome' => $pessoa->nome,
            'documento' => NormalizadorBusca::mascararDocumento($this->quickDocument),
            'tem_foto' => $pessoa->fotoVigente() !== null,
        ];
    }

    /** Validação da foto logo após a captura; ruim é descartada e pode ser refeita. */
    public function updatedFotoPessoa(ValidadorFotoPessoa $validador): void
    {
        if (! $this->podeGerenciarPessoas()) {
            $this->descartarFotoPessoa();
            $this->addError('fotoPessoa', 'Você não tem permissão para fotografar pessoas.');

            return;
        }

        $this->resetErrorBag('fotoPessoa');
        $this->validate(['fotoPessoa' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:'.ValidadorFotoPessoa::TAMANHO_MAXIMO_KB]], [
            'fotoPessoa.image' => 'Envie uma foto JPEG ou PNG.',
            'fotoPessoa.mimes' => 'Envie uma foto JPEG ou PNG.',
            'fotoPessoa.max' => 'A foto passa de 5 MB.',
        ]);

        $problema = $validador->problema((string) file_get_contents($this->fotoPessoa->getRealPath()));
        if ($problema !== null) {
            $this->descartarFotoPessoa();
            $this->addError('fotoPessoa', $problema);
        }
    }

    public function refazerFotoPessoa(): void
    {
        $this->descartarFotoPessoa();
        $this->resetErrorBag('fotoPessoa');
    }

    /** "Salvar cadastro": grava pessoa, documentos, vínculo e foto; não envia nada. */
    public function saveQuickRegistration(): void
    {
        $this->salvarCadastroRapido(enviar: false);
    }

    /** "Salvar e enviar para a facial": salva primeiro; falha no envio não desfaz o cadastro. */
    public function saveQuickRegistrationAndSend(): void
    {
        $this->salvarCadastroRapido(enviar: true);
    }

    /** @return Collection<int, array{id: string, nome: string, ponto: string, do_posto: bool, simulador: bool}> */
    public function terminaisCadastroRapido(): Collection
    {
        $usuario = Auth::user();

        return $usuario instanceof User && $usuario->hasPermission('facial.enviar')
            ? app(EnvioFacial::class)->terminaisPara($usuario)
            : collect();
    }

    // ---- Internos ---------------------------------------------------------------

    private function salvarCadastroRapido(bool $enviar): void
    {
        $this->exigirPermissoesCadastro();
        if (! $this->quickRegistrationOpen) {
            return; // segundo clique depois do salvamento: nada a fazer
        }

        $trava = Cache::lock('cadastro-rapido:'.($this->quickToken ?? Auth::id()), 30);
        if (! $trava->get()) {
            return; // o primeiro clique ainda está gravando
        }

        try {
            if (! $this->dadosConferidos()) {
                $this->quickEtapa = 'conferencia'; // o erro fica visível junto do campo

                return;
            }

            $usuario = $this->operadorCadastro();
            if ($enviar) {
                abort_unless($usuario->hasPermission('facial.enviar'), 403);
                if (! $this->terminalCadastroRapidoValido()) {
                    return;
                }
            }

            $imovel = $this->quickPropertyCode !== '' ? Imovel::query()->where('codigo', strtoupper(trim($this->quickPropertyCode)))->first() : null;
            if ($this->quickPropertyCode !== '' && $imovel === null) {
                $this->addError('quickPropertyCode', 'Imóvel não encontrado com este código.');
                $this->quickEtapa = 'conferencia';

                return;
            }

            $pessoa = $this->gravarPessoa($imovel);
            if ($pessoa === null) {
                $this->quickEtapa = 'conferencia';

                return;
            }

            $foto = $this->gravarFoto($pessoa);
            $facial = $enviar ? $this->enviarParaFacial($pessoa, $imovel, $usuario) : null;

            $this->currentPessoaId = $pessoa->id;
            $this->quickPersonRegistered = true;
            $this->quickRegistrationOpen = false;
            unset($this->currentPerson, $this->currentProtectedFiles);
            $this->quickResultado = [
                'cadastro' => $this->quickPessoaExistenteId !== null
                    ? ['variant' => 'info', 'title' => 'Cadastro existente reutilizado', 'message' => 'Nenhum dado de identidade, contato ou foto foi sobrescrito sem sua escolha.']
                    : ['variant' => 'success', 'title' => 'Cadastro salvo', 'message' => 'Pessoa, documento e vínculo registrados no SDV.'],
                'foto' => $foto,
                'facial' => $facial,
            ];
            $this->feedback = [
                'variant' => 'warning',
                'title' => 'Cadastro rápido anexado ao atendimento',
                'message' => 'Os dados mínimos foram registrados. O cadastro ainda não autoriza a entrada e deve ser complementado depois.',
            ];
            $this->limparCapturasCadastro();
        } catch (ValidationException $e) {
            $this->quickEtapa = 'conferencia';

            throw $e;
        } finally {
            $trava->release();
        }
    }

    /** Conferência humana completa e sem duplicidade pendente. */
    private function dadosConferidos(): bool
    {
        $this->descartarResponsavelForaDeMorador();
        $this->checkQuickDocument();
        if ($this->quickDuplicateFound) {
            return false;
        }

        // Dados sugeridos pelo OCR com baixa confiança exigem conferência humana.
        if ($this->camposConferencia !== []) {
            foreach ($this->camposConferencia as $campo) {
                $this->addError($campo, 'Requer conferência: confirme ou corrija este campo.');
            }

            return false;
        }

        $this->validate([
            'quickName' => ['required', 'string', 'min:3', 'max:120'],
            'quickDocument' => ['required', 'string', 'min:11', 'max:20'],
            'quickDocNumero' => ['nullable', 'string', 'max:30'],
            // Telefone e imóvel são opcionais no cadastro rápido (complemento do PO ao AJ-010); validados só quando informados.
            'quickPhone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9()+\-.\s]+$/'],
            'quickAccessType' => ['required', Rule::in(['visitante', 'prestador', 'morador', 'turista', 'outro'])],
            // Responsável só existe para morador e é opcional (docs/021 §14).
            'quickResponsible' => ['nullable', 'string', 'max:120'],
            'quickPropertyCode' => ['nullable', 'string', 'max:40'],
            'quickNotes' => ['nullable', 'string', 'max:200'],
            'quickBirthDate' => ['nullable', 'date', 'before:today'],
        ], [
            'required' => 'Preencha este campo para continuar.',
            'required_if' => 'Preencha este campo para o tipo de acesso selecionado.',
            'min' => 'Informe um valor válido.',
            'quickPhone.regex' => 'Use só números, espaços, parênteses, + ou -.',
        ]);

        $digitosTelefone = strlen(preg_replace('/\D/', '', $this->quickPhone) ?? '');
        if (trim($this->quickPhone) !== '' && ($digitosTelefone < 10 || $digitosTelefone > 13)) {
            $this->addError('quickPhone', 'Informe o telefone com DDD (10 a 13 dígitos) ou deixe em branco.');

            return false;
        }

        if (strlen($this->normalizeDocument($this->quickDocument)) !== 11) {
            $this->addError('quickDocument', 'Informe o CPF com 11 dígitos. O número do RG ou da CNH vai no campo próprio.');

            return false;
        }

        return true;
    }

    /** Pessoa nova (em transação) ou o cadastro existente escolhido; null quando houve conflito. */
    private function gravarPessoa(?Imovel $imovel): ?Pessoa
    {
        $cpf = $this->normalizeDocument($this->quickDocument);

        try {
            return DB::transaction(function () use ($imovel, $cpf): ?Pessoa {
                $existenteId = PessoaDocumento::query()->where('tipo', 'cpf')->where('valor_normalizado', $cpf)->lockForUpdate()->value('pessoa_id');

                if ($existenteId !== null) {
                    if ($existenteId !== $this->quickPessoaExistenteId) {
                        // Alguém gravou o mesmo CPF entre a conferência e o salvamento.
                        $this->quickDuplicateFound = true;
                        $this->addError('quickDocument', 'Esta pessoa já possui cadastro. Use o cadastro existente para evitar duplicidade.');

                        return null;
                    }
                    $pessoa = Pessoa::query()->findOrFail($existenteId);
                    $this->vincularSeNecessario($pessoa, $imovel);

                    return $pessoa;
                }

                $numero = NormalizadorBusca::documento($this->quickDocNumero);
                if ($numero !== '' && $this->numeroDocumentoEmUso($numero)) {
                    $this->addError('quickDocNumero', 'Este número de documento já pertence a outro cadastro. Confira o número ou busque a pessoa pelo nome.');

                    return null;
                }

                $pessoa = Pessoa::query()->create([
                    'nome' => trim($this->quickName),
                    'data_nascimento' => $this->quickBirthDate !== '' ? $this->quickBirthDate : null,
                    'status' => 'ativo',
                    'versao' => 1,
                ]);

                PessoaDocumento::query()->create([
                    'pessoa_id' => $pessoa->id,
                    'tipo' => 'cpf',
                    'pais' => 'BR',
                    'valor_normalizado' => $cpf,
                    'valor_apresentacao' => $this->quickDocument,
                    'status' => 'ativo',
                    'started_at' => now(),
                ]);

                if ($numero !== '') {
                    // O número vai com o tipo do documento lido; nunca é tratado como CPF.
                    PessoaDocumento::query()->create([
                        'pessoa_id' => $pessoa->id,
                        'tipo' => $this->capturaTipoDocumento === 'rg_cin' ? 'rg' : 'cnh',
                        'pais' => 'BR',
                        'valor_normalizado' => $numero,
                        'valor_apresentacao' => trim($this->quickDocNumero),
                        'status' => 'ativo',
                        'started_at' => now(),
                    ]);
                }

                if (trim($this->quickPhone) !== '') {
                    // Sem telefone informado, nenhum contato vazio é criado.
                    PessoaContato::query()->create([
                        'pessoa_id' => $pessoa->id,
                        'tipo' => 'telefone',
                        'valor' => trim($this->quickPhone),
                        'principal' => true,
                        'verificado' => false,
                        'started_at' => now(),
                    ]);
                }

                $this->vincularSeNecessario($pessoa, $imovel);

                return $pessoa;
            });
        } catch (UniqueConstraintViolationException) {
            // Duas gravações simultâneas do mesmo CPF (ou do mesmo número de documento).
            $numero = NormalizadorBusca::documento($this->quickDocNumero);
            if ($numero !== '' && $this->numeroDocumentoEmUso($numero) && ! PessoaDocumento::query()->where('tipo', 'cpf')->where('valor_normalizado', $cpf)->exists()) {
                $this->addError('quickDocNumero', 'Este número de documento acabou de ser cadastrado em outra pessoa. Confira o número.');

                return null;
            }

            $this->quickDuplicateFound = true;
            $this->addError('quickDocument', 'Este documento acabou de ser cadastrado. Use o cadastro existente para evitar duplicidade.');

            return null;
        }
    }

    private function numeroDocumentoEmUso(string $numero): bool
    {
        return PessoaDocumento::query()
            ->where('tipo', $this->capturaTipoDocumento === 'rg_cin' ? 'rg' : 'cnh')
            ->where('pais', 'BR')
            ->where('valor_normalizado', $numero)
            ->exists();
    }

    /** Turista não ganha vínculo artificial com imóvel; reutilizado mantém o vínculo que já tem. */
    private function vincularSeNecessario(Pessoa $pessoa, ?Imovel $imovel): void
    {
        if ($imovel === null || $this->quickAccessType === 'turista') {
            return;
        }

        $jaVinculado = Vinculo::query()
            ->where('pessoa_id', $pessoa->id)
            ->where('imovel_id', $imovel->id)
            ->where('status', 'ativo')
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', now()))
            ->exists();
        if ($jaVinculado) {
            return;
        }

        Vinculo::query()->create([
            'pessoa_id' => $pessoa->id,
            'imovel_id' => $imovel->id,
            'tipo' => $this->quickAccessType,
            'papel' => 'outro',
            'status' => 'ativo',
            'origem' => 'cadastro_manual',
            'started_at' => now(),
            'versao' => 1,
        ]);
    }

    /** @return array{variant: string, title: string, message: string} */
    private function gravarFoto(Pessoa $pessoa): array
    {
        if ($this->fotoPessoa === null) {
            return ['variant' => 'info', 'title' => 'Sem foto da pessoa', 'message' => 'Nenhuma foto foi capturada neste atendimento.'];
        }

        if ($pessoa->fotoVigente() !== null && ! $this->quickSubstituirFoto) {
            $this->descartarFotoPessoa();

            return ['variant' => 'info', 'title' => 'Foto do cadastro mantida', 'message' => 'A pessoa já tinha foto e você não pediu a troca; a captura deste atendimento foi descartada.'];
        }

        try {
            app(PrivateFileService::class)->storePessoaFoto($pessoa, $this->fotoPessoa, 'cadastro_rapido');
        } catch (Throwable) {
            // O cadastro continua salvo; a foto pode ser capturada de novo no cadastro da pessoa.
            return ['variant' => 'danger', 'title' => 'Foto não salva', 'message' => 'O cadastro foi salvo, mas a foto não pôde ser armazenada. Fotografe novamente no cadastro da pessoa.'];
        } finally {
            $this->descartarFotoPessoa();
        }

        return ['variant' => 'success', 'title' => 'Foto salva', 'message' => 'Foto atual guardada no armazenamento privado da pessoa, separada do documento.'];
    }

    /** @return array<string, mixed> */
    private function enviarParaFacial(Pessoa $pessoa, ?Imovel $imovel, User $usuario): array
    {
        $autorizacao = null;
        // Visitante e turista: visita própria de no máximo 24 horas (AJ-006). Visitante sem imóvel
        // não ganha visita automática: o envio segue a autorização vigente que já existir.
        $criaVisita = $this->quickAccessType === 'turista' || ($this->quickAccessType === 'visitante' && $imovel !== null);
        if ($criaVisita) {
            $autorizacoes = app(AutorizacoesAcesso::class);
            $autorizacao = $autorizacoes->vigentePara($pessoa);
            if ($autorizacao === null || $autorizacao->tipo !== AutorizacaoAcesso::TIPO_TURISTA) {
                try {
                    $autorizacao = $autorizacoes->registrarVisitaTurista($pessoa, $imovel, $this->quickResponsible, null, null, $usuario);
                } catch (RegraIntegracaoViolada $e) {
                    return ['variant' => 'danger', 'title' => 'Envio para a facial não realizado', 'message' => $e->getMessage().' O cadastro no SDV continua salvo.', 'avisos' => [], 'foto' => null];
                }
            }
        }

        $resultado = app(EnvioFacial::class)->enviar($pessoa, $this->quickTerminalId, $usuario, $autorizacao)->paraTela();
        $this->quickTerminalConfirmado = false;

        return $resultado;
    }

    private function terminalCadastroRapidoValido(): bool
    {
        if ($this->quickTerminalId === '' || $this->terminaisCadastroRapido()->firstWhere('id', $this->quickTerminalId) === null) {
            $this->addError('quickTerminalId', 'Escolha o terminal facial que vai receber o cadastro.');

            return false;
        }
        if (! $this->quickTerminalConfirmado) {
            $this->addError('quickTerminalConfirmado', 'Confirme o terminal escolhido antes de enviar.');

            return false;
        }

        return true;
    }

    /** Terminal do posto (ou o único possível) vem escolhido; o envio ainda exige confirmação. */
    private function preSelecionarTerminal(): void
    {
        $terminais = $this->terminaisCadastroRapido();
        $doPosto = $terminais->where('do_posto', true);
        $unico = $doPosto->count() === 1 ? $doPosto->first() : ($terminais->count() === 1 ? $terminais->first() : null);
        $this->quickTerminalId = $unico['id'] ?? '';
        $this->quickTerminalConfirmado = false;
    }

    /** Leitura útil leva direto à conferência (chamado pela captura do documento). */
    protected function avancarParaConferencia(): void
    {
        $this->exigirPermissoesCadastro();
        $this->quickEtapa = 'conferencia';
    }

    private function descartarFotoPessoa(): void
    {
        $this->fotoPessoa?->delete();
        $this->fotoPessoa = null;
    }

    /** Cancelar ou novo atendimento: nenhuma captura, sugestão ou prévia sobrevive; a câmera fecha. */
    private function limparCapturasCadastro(): void
    {
        $this->descartarFotoPessoa();
        $this->descartarArquivosTemporarios();
        $this->analiseDocumento = null;
        $this->camposConferencia = [];
        $this->camposEditados = [];
        $this->avisoLeitura = null;
        $this->quickSubstituirFoto = false;
        $this->dispatch('cadastro-rapido-encerrado');
    }

    private function resetQuickRegistrationFields(): void
    {
        $this->quickName = '';
        $this->quickDocument = '';
        $this->quickDocNumero = '';
        $this->quickPhone = '';
        $this->quickAccessType = 'visitante';
        $this->quickResponsible = '';
        $this->quickPropertyCode = '';
        $this->quickNotes = '';
        $this->quickBirthDate = '';
        $this->quickEtapa = 'documento';
        $this->quickPessoaExistenteId = null;
        $this->quickToken = null;
        $this->quickTerminalId = '';
        $this->quickTerminalConfirmado = false;
        $this->capturaTipoDocumento = 'cnh';
        $this->capturaOrigem = 'camera';
        $this->limparCapturasCadastro();
    }

    private function podeGerenciarPessoas(): bool
    {
        $usuario = Auth::user();

        return $usuario instanceof User && $usuario->hasPermission('pessoas.gerenciar');
    }

    private function exigirPermissoesCadastro(): void
    {
        $usuario = $this->operadorCadastro();
        abort_unless($usuario->hasPermission('validacao.registrar') && $usuario->hasPermission('pessoas.gerenciar'), 403);
    }

    private function operadorCadastro(): User
    {
        $usuario = Auth::user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }

    private function normalizeDocument(string $document): string
    {
        return preg_replace('/\D/', '', $document) ?? '';
    }
}

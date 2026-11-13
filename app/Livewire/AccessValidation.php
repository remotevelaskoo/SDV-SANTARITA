<?php

namespace App\Livewire;

use App\Integracoes\Aplicacao\CamerasPlaca;
use App\Integracoes\Dominio\Excecoes\RegraIntegracaoViolada;
use App\Livewire\Concerns\CadastroRapidoAtendimento;
use App\Livewire\Concerns\CapturaDocumentoAssistida;
use App\Livewire\Concerns\ConferenciaDadosIdentificacao;
use App\Livewire\Concerns\FluxoGuiadoAtendimento;
use App\Models\CaixaTurno;
use App\Models\CapturaPlaca;
use App\Models\Catalogo;
use App\Models\CatalogoItem;
use App\Models\Contribuicao;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\PreRegistrationArquivo;
use App\Models\VeiculoVinculo;
use App\Models\Vinculo;
use App\Services\AuditService;
use App\Services\BuscaPessoasValidacao;
use App\Services\ContribuicoesAtendimento;
use App\Services\FotoPessoaResolver;
use App\Services\PerfilAtendimento;
use App\Services\PreCadastro\CatalogoDestinos;
use App\Services\PrivateFileService;
use App\Services\ValidadorFotoPessoa;
use App\Support\NormalizadorBusca;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AccessValidation extends Component
{
    use CadastroRapidoAtendimento, CapturaDocumentoAssistida, ConferenciaDadosIdentificacao, FluxoGuiadoAtendimento {
        CadastroRapidoAtendimento::avancarParaConferencia insteadof CapturaDocumentoAssistida;
    }

    private const PONTO_ACESSO = 'Portaria Principal';

    /** Placa antiga (ABC1234) ou Mercosul (ABC1D23). */
    public const PLACA_REGEX = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/';

    /** Câmera escolhida pelo operador; só o identificador interno chega ao navegador (AJ-006). */
    public string $cameraPlacaId = '';

    #[Locked]
    public ?string $capturaPlacaId = null;

    public ?string $capturaPlacaFalha = null;

    /** Placa digitada ou conferida sobre a imagem; contingência quando não há câmera. */
    public string $placaInformada = '';

    public string $contribution = 'yes';

    public string $paymentMethod = 'dinheiro';

    public string $notes = '';

    /** Número do ticket de acesso: texto, preserva zeros à esquerda (AJ-006). */
    public string $ticketNumero = '';

    public bool $ticketDuplicadoConfirmado = false;

    public string $denialReason = 'sem_autorizacao';

    public string $denialDetails = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public ?string $protocol = null;

    public bool $documentRevealed = false;

    /**
     * Pessoa do atendimento. Só muda pelos métodos do servidor (seleção na
     * busca ou cadastro rápido); nunca é escolhida automaticamente (AJ-006).
     */
    #[Locked]
    public ?string $currentPessoaId = null;

    /** Termo da busca de "Identificação da pessoa" (nome, documento, telefone, protocolo ou imóvel). */
    public string $busca = '';

    /**
     * Foto tirada na identificação quando a pessoa selecionada não tem foto.
     * Mesma câmera do cadastro rápido (AJ-010); vira a foto do cadastro só em
     * "Salvar foto". Não é enviada ao terminal facial.
     */
    public ?TemporaryUploadedFile $fotoIdentificacao = null;

    public bool $fotoIdentificacaoSalva = false;

    /** Aviso de que o atendimento veio de um pré-cadastro aprovado (some no novo atendimento). */
    #[Locked]
    public ?array $avisoPreCadastro = null;

    /** Tipo de acesso do pré-cadastro aprovado que abriu este atendimento. */
    #[Locked]
    public ?string $tipoPreCadastro = null;

    /**
     * "Aprovar e validar entrada" na análise abre esta tela com
     * ?pre-cadastro={id}. Só traz dados para conferência: não libera a
     * entrada, não cria autorização e não envia à facial.
     */
    public function mount(AuditService $audit): void
    {
        $id = request()->query('pre-cadastro');
        if (is_string($id) && $id !== '' && strlen($id) <= 64) {
            $this->carregarPreCadastroAprovado($id, $audit);
        }
    }

    private function carregarPreCadastroAprovado(string $id, AuditService $audit): void
    {
        $usuario = Auth::user();
        abort_unless($usuario?->hasPermission('validacao.registrar'), 403);

        // Escopo da implantação ativa: id de outra implantação não existe aqui.
        $pre = PreRegistration::query()->whereKey($id)->first();
        if ($pre === null || $pre->status !== 'aprovado') {
            $this->avisoPreCadastro = [
                'variant' => 'warning',
                'title' => 'Pré-cadastro não disponível',
                'message' => 'Só um pré-cadastro aprovado abre a validação. Busque a pessoa pelo nome ou CPF.',
            ];

            return;
        }

        $this->tipoPreCadastro = $pre->access_type;
        $cpf = preg_replace('/\D/', '', (string) $pre->document) ?? '';
        $pessoaId = strlen($cpf) === 11 ? PessoaDocumento::query()
            ->where('tipo', 'cpf')
            ->where('valor_normalizado', $cpf)
            ->whereNull('ended_at')
            ->value('pessoa_id') : null;

        if ($pessoaId !== null) {
            $this->currentPessoaId = $pessoaId;
            $resultado = 'cadastro_existente';
            $this->avisoPreCadastro = [
                'variant' => 'info',
                'title' => 'Pessoa do pré-cadastro '.$pre->protocol,
                'message' => 'O CPF já tem cadastro no SDV e ele foi aberto. Confira o documento original e a autorização antes de decidir.',
            ];
        } elseif ($usuario->hasPermission('pessoas.gerenciar')) {
            $this->openQuickRegistration();
            $this->preencherCadastroRapido($pre);
            $resultado = 'cadastro_rapido';
            $this->avisoPreCadastro = [
                'variant' => 'info',
                'title' => 'Dados do pré-cadastro '.$pre->protocol,
                'message' => 'Os dados aprovados já estão no cadastro rápido. Confira com o documento original, tire a foto e salve. Nada foi gravado e a entrada não foi liberada.',
            ];
        } else {
            $this->busca = $pre->protocol;
            $resultado = 'sem_permissao_cadastro';
            $this->avisoPreCadastro = [
                'variant' => 'warning',
                'title' => 'Pessoa ainda sem cadastro',
                'message' => 'O pré-cadastro '.$pre->protocol.' foi aprovado, mas a pessoa ainda não tem cadastro no SDV. Peça a quem pode cadastrar pessoas para concluir o cadastro.',
            ];
        }

        $audit->record(
            action: 'abriu_validacao_pelo_pre_cadastro',
            module: 'validacao',
            entityType: 'pre_registrations',
            entityId: $pre->id,
            metadata: ['protocolo' => $pre->protocol, 'resultado' => $resultado],
        );
    }

    /** Só copia o que o pré-cadastro aprovado traz; imóvel só entra se ainda estiver no catálogo. */
    private function preencherCadastroRapido(PreRegistration $pre): void
    {
        $this->quickName = (string) $pre->name;
        $this->quickDocument = (string) $pre->document;
        $this->quickPhone = (string) $pre->phone;
        $this->quickBirthDate = (string) $pre->birth_date?->format('Y-m-d');
        $this->quickAccessType = in_array($pre->access_type, ['visitante', 'prestador', 'turista'], true) ? $pre->access_type : 'visitante';
        $this->quickNotes = mb_substr('Pré-cadastro '.$pre->protocol, 0, 200);
        $this->quickEtapa = 'conferencia';

        // Prestador e turista: sem responsável nem vínculo com imóvel criados aqui (docs/021).
        if ($this->quickAccessType === 'visitante') {
            $this->quickResponsible = (string) $pre->responsible_name;
            $imovelId = app(CatalogoDestinos::class)->imovelId($pre->destination_property);
            $this->quickPropertyCode = $imovelId !== null ? (string) Imovel::query()->whereKey($imovelId)->value('codigo') : '';
        }

        // A selfie enviada no pré-cadastro vira a foto da pessoa: não se pede de novo.
        $selfie = $pre->currentFileLink('selfie')?->file;
        $this->quickSelfiePreCadastroId = $selfie?->isAvailable() ? $selfie->id : null;

        if ($pre->vehicle_plate) {
            $this->placaInformada = NormalizadorBusca::documento((string) $pre->vehicle_plate);
            $this->veioDeCarro = true;
        }

        $this->checkQuickDocument();
    }

    public function selecionarPessoa(string $pessoaId, AuditService $audit): void
    {
        abort_unless(Auth::user()?->hasPermission('validacao.registrar'), 403);

        // O escopo da implantação ativa vale também aqui: id de outra implantação não existe.
        $pessoa = Pessoa::query()->find($pessoaId);
        abort_if($pessoa === null, 404);

        $this->currentPessoaId = $pessoa->id;
        $this->quickPersonRegistered = false;
        $this->quickResultado = null;
        $this->documentRevealed = false;
        $this->busca = '';
        $this->encerrarFotoIdentificacao();
        $this->reiniciarTipoAtendimento();
        unset($this->currentPerson, $this->currentProtectedFiles);

        $audit->record(
            action: 'selecionou_pessoa',
            module: 'validacao',
            entityType: 'pessoas',
            entityId: $pessoa->id,
            metadata: ['origem' => 'busca'],
        );
    }

    public function trocarPessoa(): void
    {
        $this->passo = 'dados';
        $this->currentPessoaId = null;
        $this->quickPersonRegistered = false;
        $this->quickResultado = null;
        $this->documentRevealed = false;
        $this->encerrarFotoIdentificacao();
        $this->reiniciarTipoAtendimento();
        unset($this->currentPerson, $this->currentProtectedFiles);
    }

    /** Conferência da foto logo após a captura; foto ruim é descartada para refazer. */
    public function updatedFotoIdentificacao(ValidadorFotoPessoa $validador): void
    {
        if (! $this->podeTirarFotoIdentificacao()) {
            $this->refazerFotoIdentificacao();
            $this->addError('fotoIdentificacao', 'Você não tem permissão para fotografar pessoas.');

            return;
        }

        $this->resetErrorBag('fotoIdentificacao');
        $this->validate(['fotoIdentificacao' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:'.ValidadorFotoPessoa::TAMANHO_MAXIMO_KB]], [
            'fotoIdentificacao.required' => 'Fotografe a pessoa antes de salvar.',
            'fotoIdentificacao.image' => 'Envie uma foto JPEG ou PNG.',
            'fotoIdentificacao.mimes' => 'Envie uma foto JPEG ou PNG.',
            'fotoIdentificacao.max' => 'A foto passa de 5 MB.',
        ]);

        $problema = $validador->problema((string) file_get_contents($this->fotoIdentificacao->getRealPath()));
        if ($problema !== null) {
            $this->refazerFotoIdentificacao();
            $this->addError('fotoIdentificacao', $problema);
        }
    }

    public function refazerFotoIdentificacao(): void
    {
        $this->fotoIdentificacao?->delete();
        $this->fotoIdentificacao = null;
        $this->resetErrorBag('fotoIdentificacao');
    }

    /**
     * Grava a foto como foto vigente do cadastro da pessoa selecionada (sem
     * envio à facial). Quem já tinha foto ganha a nova, e a anterior fica no
     * histórico (pedido do PO de 13/11/2026: o porteiro pode tirar nova foto).
     */
    public function salvarFotoIdentificacao(PrivateFileService $files, ValidadorFotoPessoa $validador): void
    {
        abort_unless($this->podeTirarFotoIdentificacao(), 403);

        $pessoa = Pessoa::query()->find($this->currentPessoaId);
        abort_if($pessoa === null, 404);

        if ($this->fotoIdentificacao === null) {
            $this->addError('fotoIdentificacao', 'Fotografe a pessoa antes de salvar.');

            return;
        }

        $problema = $validador->problema((string) file_get_contents($this->fotoIdentificacao->getRealPath()));
        if ($problema !== null) {
            $this->refazerFotoIdentificacao();
            $this->addError('fotoIdentificacao', $problema);

            return;
        }

        $files->storePessoaFoto($pessoa, $this->fotoIdentificacao, 'validacao_portaria');

        $this->encerrarFotoIdentificacao();
        unset($this->currentPerson);
        $this->fotoIdentificacaoSalva = true;
    }

    private function podeTirarFotoIdentificacao(): bool
    {
        $usuario = Auth::user();

        return $this->currentPessoaId !== null
            && (bool) $usuario?->hasPermission('validacao.registrar')
            && (bool) $usuario?->hasPermission('pessoas.gerenciar');
    }

    private function encerrarFotoIdentificacao(): void
    {
        $this->encerrarEdicaoIdentificacao();
        $this->fotoIdentificacaoSalva = false;
        $this->refazerFotoIdentificacao();
        $this->dispatch('foto-identificacao-encerrada');
    }

    /** @return array{resultados: list<array<string, mixed>>, mais: bool, criterios: list<string>} */
    #[Computed]
    public function resultadosBusca(): array
    {
        if (! Auth::user()?->hasPermission('validacao.registrar')) {
            return ['resultados' => [], 'mais' => false, 'criterios' => []];
        }

        return app(BuscaPessoasValidacao::class)->buscar($this->busca, $this->canViewImage());
    }

    public function deny(): void
    {
        if ($this->atendimentoJaRegistrado()) {
            return;
        }

        $this->validate([
            'denialReason' => ['required', Rule::in($this->denialReasonCodigos())],
            'denialDetails' => ['nullable', 'string', 'max:200'],
        ]);

        $historico = $this->recordHistorico('negado', $this->denialReasonLabel());

        $this->feedback = [
            'variant' => 'danger',
            'title' => 'Entrada negada e registrada',
            'message' => 'Nenhum comando de abertura foi enviado.',
        ];
        $this->protocol = $historico->protocol;
    }

    public function savePending(): void
    {
        if ($this->atendimentoJaRegistrado()) {
            return;
        }

        $this->validateCommonFields();

        $historico = $this->recordHistoricoComContribuicao('pendente');

        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Atendimento salvo sem liberação',
            'message' => 'Os dados foram preservados para continuação posterior. Nenhum comando foi enviado.',
        ];
        $this->protocol = $historico->protocol;
    }

    public function release(): void
    {
        if ($this->atendimentoJaRegistrado()) {
            return;
        }

        $this->validateCommonFields();

        if ($this->quickPersonRegistered) {
            $historico = $this->recordHistoricoComContribuicao('pendente');
            $this->feedback = [
                'variant' => 'warning',
                'title' => 'Liberação não realizada',
                'message' => 'O cadastro rápido não possui autorização válida. Salve o atendimento ou confirme a autorização por outro fluxo.',
            ];
            $this->protocol = $historico->protocol;

            return;
        }

        $historico = $this->recordHistoricoComContribuicao('liberado');

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Validação concluída',
            'message' => 'O registro foi salvo. Nenhum portão ou equipamento real foi acionado.',
        ];
        $this->protocol = $historico->protocol;
    }

    /**
     * Captura estática sob demanda (AJ-006). A imagem vira evidência privada;
     * a leitura da placa é outra etapa e a captura não decide nada.
     */
    public function capturarPlaca(CamerasPlaca $cameras): void
    {
        abort_unless(Auth::user()?->hasPermission('validacao.registrar'), 403);
        $this->capturaPlacaFalha = null;

        if (! collect($this->camerasPlaca)->contains('id', $this->cameraPlacaId)) {
            $this->addError('cameraPlacaId', 'Escolha uma câmera deste posto.');

            return;
        }

        try {
            $resultado = $cameras->capturar($this->cameraPlacaId, Auth::user());
        } catch (RegraIntegracaoViolada $e) {
            $this->capturaPlacaFalha = $e->getMessage();

            return;
        }

        if (! $resultado['ok']) {
            $this->capturaPlacaFalha = $resultado['mensagem'].' Você pode digitar a placa.';

            return;
        }

        $this->capturaPlacaId = $resultado['captura']->id;
        unset($this->capturaPlaca);
    }

    public function descartarCapturaPlaca(): void
    {
        $this->capturaPlacaId = null;
        $this->capturaPlacaFalha = null;
        unset($this->capturaPlaca);
    }

    public function updatedPlacaInformada(): void
    {
        $this->placaInformada = NormalizadorBusca::documento($this->placaInformada);
    }

    /** @return list<array{id: string, nome: string, ponto: string, ponto_id: string, simulador: bool}> */
    #[Computed]
    public function camerasPlaca(): array
    {
        $usuario = Auth::user();
        if (! $usuario?->hasPermission('validacao.registrar')) {
            return [];
        }

        return app(CamerasPlaca::class)->disponiveisPara($usuario)->all();
    }

    /** @return array{id: string, camera: string, ponto: ?string, capturada_em: string, simulada: bool, url: ?string}|null */
    #[Computed]
    public function capturaPlaca(): ?array
    {
        if ($this->capturaPlacaId === null) {
            return null;
        }

        $captura = CapturaPlaca::query()
            ->with(['equipamento', 'pontoAcesso', 'arquivo'])
            ->where('operador_id', Auth::id())
            ->find($this->capturaPlacaId);

        return $captura === null ? null : [
            'id' => $captura->id,
            'camera' => $captura->equipamento?->nome ?? 'Câmera',
            'ponto' => $captura->pontoAcesso?->nome,
            'capturada_em' => $captura->capturada_em->format('d/m/Y \à\s H:i:s'),
            'simulada' => $captura->simulada(),
            'url' => $this->canViewImage() && $captura->arquivo?->isAvailable()
                ? route('protected-files.show', ['arquivo' => $captura->arquivo_id, 'contexto' => 'evidencia_placa'])
                : null,
        ];
    }

    public function startNewValidation(): void
    {
        $this->reset([
            'notes', 'denialDetails', 'feedback', 'protocol', 'quickRegistrationOpen', 'quickPersonRegistered',
            'ticketNumero', 'ticketDuplicadoConfirmado', 'capturaPlacaId', 'capturaPlacaFalha', 'placaInformada',
            'quickDuplicateFound', 'quickResultado', 'avisoPreCadastro', 'tipoPreCadastro',
        ]);
        // Novo atendimento: nenhuma captura, prévia ou dado do cadastro anterior sobrevive.
        $this->resetQuickRegistrationFields();
        $this->contribution = 'yes';
        $this->paymentMethod = 'dinheiro';
        $this->denialReason = 'sem_autorizacao';
        $this->currentPessoaId = null;
        $this->busca = '';
        $this->documentRevealed = false;
        $this->encerrarFotoIdentificacao();
        $this->reiniciarFluxoGuiado();
    }

    public function toggleDocumentReveal(AuditService $audit): void
    {
        abort_unless(Auth::user()?->hasPermission('dados-sensiveis.revelar'), 403);

        if ($this->documentRevealed) {
            $this->documentRevealed = false;

            return;
        }

        abort_unless($this->currentPessoaId !== null, 404);
        $this->documentRevealed = true;

        $audit->record(
            action: 'revelou_dado_sensivel',
            module: 'validacao',
            entityType: 'pessoas',
            entityId: $this->currentPessoaId,
            classification: 'restrita',
            metadata: ['categoria' => 'documento'],
        );
    }

    /**
     * Pessoa selecionada com vínculos, veículos, foto e alertas (AJ-006).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function currentPerson(): array
    {
        $pessoa = $this->currentPessoaId
            ? Pessoa::query()->with(['documentos' => fn ($q) => $q->whereNull('ended_at')])->find($this->currentPessoaId)
            : null;

        if (! $pessoa) {
            return [
                'selected' => false,
                'name' => 'Nenhuma pessoa selecionada',
                'initials' => '—',
                'document' => 'Não informado',
                'type' => 'Não identificado',
                'property' => 'Não vinculado',
                'responsible' => 'A confirmar',
                'status' => 'Sem cadastro',
                'validity' => '—',
                'tone' => 'neutral',
                'photo_url' => null,
                'photo_state' => 'sem_foto',
                'photo_origin' => null,
                'can_take_photo' => false,
                'has_photo' => false,
                'links' => [],
                'vehicles' => [],
                'alerts' => [],
                'checks' => [],
            ];
        }

        $vinculos = Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->with('imovel')->orderBy('started_at')->get();
        $vinculo = $vinculos->first();
        $documento = $pessoa->documentos->first()?->valor_apresentacao;
        $fotoExistente = app(FotoPessoaResolver::class)->paraPessoa($pessoa);
        $foto = $this->canViewImage() ? $fotoExistente : null;
        $podeTirarFoto = $fotoExistente === null && $this->podeTirarFotoIdentificacao();
        $veiculos = VeiculoVinculo::query()
            ->where('pessoa_id', $pessoa->id)
            ->whereNull('ended_at')
            ->with('veiculo')
            ->get()
            ->filter(fn (VeiculoVinculo $vinculoVeiculo) => $vinculoVeiculo->veiculo !== null)
            ->map(fn (VeiculoVinculo $vinculoVeiculo) => [
                'plate' => $vinculoVeiculo->veiculo->plate_display,
                'description' => collect([$vinculoVeiculo->veiculo->brand, $vinculoVeiculo->veiculo->model, $vinculoVeiculo->veiculo->color])->filter()->join(' · ') ?: 'Sem descrição',
                'status' => $vinculoVeiculo->veiculo->status,
            ])
            ->values()
            ->all();

        $alertas = [];
        if ($pessoa->status !== 'ativo') {
            $alertas[] = ['tone' => 'danger', 'text' => 'Cadastro '.str_replace('_', ' ', (string) $pessoa->status).'. Confirme antes de liberar.'];
        }
        if ($vinculo === null) {
            $alertas[] = ['tone' => 'warning', 'text' => 'Sem vínculo vigente com imóvel.'];
        } elseif ($vinculo->status !== 'ativo') {
            $alertas[] = ['tone' => 'warning', 'text' => 'Vínculo '.str_replace('_', ' ', (string) $vinculo->status).'.'];
        }
        if ($documento === null) {
            $alertas[] = ['tone' => 'warning', 'text' => 'Nenhum documento ativo no cadastro.'];
        }
        if ($foto === null && $this->canViewImage() && ! $podeTirarFoto) {
            $alertas[] = ['tone' => 'info', 'text' => 'Sem foto cadastrada: confira o documento original.'];
        }

        $cadastroOk = $pessoa->status === 'ativo' && ! $this->quickPersonRegistered;
        $vinculoOk = $vinculo !== null && $vinculo->status === 'ativo';

        return [
            'selected' => true,
            'name' => $pessoa->nomeExibicao(),
            'initials' => BuscaPessoasValidacao::iniciais($pessoa->nome),
            'document' => $this->documentRevealed
                ? ($documento ?? 'Não informado')
                : NormalizadorBusca::mascararDocumento($documento),
            'type' => BuscaPessoasValidacao::rotuloVinculo($vinculo?->tipo),
            'property' => $vinculo?->imovel?->label() ?? 'Não vinculado',
            'responsible' => $this->quickPersonRegistered && $this->quickResponsible !== '' ? $this->quickResponsible : null,
            'status' => $this->quickPersonRegistered ? 'Cadastro mínimo' : ($pessoa->status === 'ativo' ? 'Cadastro ativo' : 'Cadastro '.$pessoa->status),
            'validity' => $this->quickPersonRegistered
                ? 'Aguardando análise'
                : ($vinculo ? BuscaPessoasValidacao::vigencia($vinculo->started_at, $vinculo->ended_at) : 'Sem vínculo vigente'),
            'tone' => $this->quickPersonRegistered || ! $cadastroOk || ! $vinculoOk ? 'warning' : 'success',
            'photo_url' => $foto ? route('protected-files.show', ['arquivo' => $foto['arquivo'], 'contexto' => 'validacao']) : null,
            'photo_state' => $foto ? 'disponivel' : ($this->canViewImage() ? 'sem_foto' : 'sem_permissao'),
            'photo_origin' => $foto ? ($foto['origem'] === 'cadastro' ? 'Foto do cadastro' : 'Selfie do pré-cadastro aprovado') : null,
            // Pode fotografar mesmo quem já tem foto: a nova substitui no Concluir.
            'can_take_photo' => $this->podeTirarFotoIdentificacao(),
            'has_photo' => $fotoExistente !== null,
            'links' => $vinculos->map(fn (Vinculo $item) => [
                'type' => BuscaPessoasValidacao::rotuloVinculo($item->tipo),
                'property' => $item->imovel?->label() ?? 'Sem imóvel',
                'validity' => BuscaPessoasValidacao::vigencia($item->started_at, $item->ended_at),
                'status' => $item->status,
            ])->all(),
            'vehicles' => $veiculos,
            'alerts' => $alertas,
            'checks' => [
                ['label' => 'Cadastro', 'value' => $this->quickPersonRegistered ? 'Mínimo e provisório' : ($cadastroOk ? 'Ativo' : 'Requer atenção'), 'ok' => $cadastroOk],
                ['label' => 'Vínculo', 'value' => $vinculoOk ? BuscaPessoasValidacao::rotuloVinculo($vinculo->tipo).' vigente' : 'Sem vínculo vigente', 'ok' => $vinculoOk],
                ['label' => 'Autorização', 'value' => 'Conferir pelo vínculo e pelo responsável', 'ok' => null],
                ['label' => 'Documento', 'value' => $documento ? 'Cadastrado (conferência visual)' : 'Não cadastrado', 'ok' => $documento ? null : false],
                ['label' => 'Facial', 'value' => 'Não sincronizada', 'ok' => null],
            ],
        ];
    }

    private function canViewImage(): bool
    {
        return (bool) Auth::user()?->hasPermission('arquivos.sensiveis.visualizar');
    }

    /** @return array{document: ?PreRegistrationArquivo, document_back: ?PreRegistrationArquivo, document_portrait: ?PreRegistrationArquivo, selfie: ?PreRegistrationArquivo, pre_registration_id: ?string} */
    #[Computed]
    public function currentProtectedFiles(): array
    {
        if ($this->quickPersonRegistered || ! $this->currentPessoaId) {
            return ['document' => null, 'document_back' => null, 'document_portrait' => null, 'selfie' => null, 'pre_registration_id' => null];
        }

        $presentedDocument = PessoaDocumento::query()
            ->where('pessoa_id', $this->currentPessoaId)
            ->where('status', 'ativo')
            ->whereNull('ended_at')
            ->value('valor_apresentacao');

        if (! $presentedDocument) {
            return ['document' => null, 'document_back' => null, 'document_portrait' => null, 'selfie' => null, 'pre_registration_id' => null];
        }

        $preRegistration = PreRegistration::query()
            ->where('document', $presentedDocument)
            ->where('status', 'aprovado')
            ->with('fileLinks.file')
            ->latest('submitted_at')
            ->first();

        return [
            'document' => $preRegistration?->currentFileLink('documento'),
            'document_back' => $preRegistration?->currentFileLink('documento_verso'),
            'document_portrait' => $preRegistration?->currentFileLink('documento_foto'),
            'selfie' => $preRegistration?->currentFileLink('selfie'),
            'pre_registration_id' => $preRegistration?->id,
        ];
    }

    /**
     * Um atendimento registrado encerra esta tela: novo clique não grava outro
     * atendimento nem outra contribuição (o mesmo ticket apareceria como
     * "já registrado" no próprio atendimento). Segue em "Iniciar nova validação".
     */
    private function atendimentoJaRegistrado(): bool
    {
        if ($this->protocol === null) {
            return false;
        }

        $this->addError('atendimento', 'Este atendimento já foi registrado ('.$this->protocol.'). Use "Iniciar nova validação" para atender outra pessoa.');

        return true;
    }

    public function updatedTicketNumero(): void
    {
        $this->ticketDuplicadoConfirmado = false;
        unset($this->ticketDuplicado);
    }

    /** @return array{quando: string, protocolo: ?string}|null */
    #[Computed]
    public function ticketDuplicado(): ?array
    {
        $ticket = ContribuicoesAtendimento::normalizarTicket($this->ticketNumero);
        // A contribuição deste mesmo atendimento, já registrado, não é "outro atendimento".
        $propria = $this->protocol !== null
            ? Contribuicao::query()->whereHas('historicoAcesso', fn ($q) => $q->where('protocol', $this->protocol))->value('id')
            : null;
        $existente = $ticket !== null ? Contribuicao::ticketJaUsado($ticket, $propria) : null;

        return $existente ? [
            'quando' => $existente->registrada_em->format('d/m/Y H:i'),
            'protocolo' => $existente->historicoAcesso?->protocol,
        ] : null;
    }

    #[Computed]
    public function caixaAberto(): bool
    {
        return CaixaTurno::query()->where('operator_id', Auth::id())->where('status', 'aberto')->exists();
    }

    /**
     * Perfil da pessoa atendida agora (Turista, Visitante, Morador...).
     *
     * @return array{perfil: string, local: string, turista: bool}
     */
    public function perfilAtual(): array
    {
        $perfil = app(PerfilAtendimento::class)->daPessoa($this->currentPessoaId, now());

        // Pessoa sem visita nem vínculo (ou ainda no cadastro rápido): vale o tipo
        // declarado neste atendimento (cadastro rápido ou pré-cadastro aprovado).
        // Quem tem vínculo nunca vira turista por declaração.
        if (! $perfil['turista'] && $perfil['perfil'] === 'Sem vínculo' && $this->tipoDeclarado() === 'turista') {
            return ['perfil' => PerfilAtendimento::TURISTA, 'local' => PerfilAtendimento::PRAIA, 'turista' => true];
        }

        // Pessoa já cadastrada, sem vínculo: o tipo escolhido pelo porteiro na etapa Dados.
        if ($perfil['perfil'] === 'Sem vínculo' && ! $this->quickRegistrationOpen && ! $this->quickPersonRegistered && isset(self::TIPOS_ATENDIMENTO[$this->tipoAtendimento])) {
            return ['perfil' => self::TIPOS_ATENDIMENTO[$this->tipoAtendimento], 'local' => '—', 'turista' => false];
        }

        return $perfil;
    }

    private function tipoDeclarado(): ?string
    {
        if ($this->quickRegistrationOpen || $this->quickPersonRegistered) {
            return $this->quickAccessType;
        }

        return $this->tipoAtendimento !== '' ? $this->tipoAtendimento : $this->tipoPreCadastro;
    }

    /** Regra do PO (11/11/2026): só turista contribui; os demais não têm contribuição. */
    private function contribuiNesteAtendimento(): bool
    {
        return $this->perfilAtual()['turista'];
    }

    /** Atendimento e contribuição na mesma transação: ticket inválido não deixa atendimento órfão. */
    private function recordHistoricoComContribuicao(string $resultado): HistoricoAcesso
    {
        if (! $this->contribuiNesteAtendimento()) {
            return $this->recordHistorico($resultado);
        }

        return DB::transaction(function () use ($resultado): HistoricoAcesso {
            $historico = $this->recordHistorico($resultado);

            app(ContribuicoesAtendimento::class)->registrar(
                $historico,
                ContribuicoesAtendimento::DA_TELA[$this->contribution],
                $this->paymentMethod,
                $this->ticketNumero,
                $this->ticketDuplicadoConfirmado,
                Auth::user(),
            );

            return $historico;
        });
    }

    private function recordHistorico(string $resultado, ?string $motivo = null): HistoricoAcesso
    {
        $placa = NormalizadorBusca::documento($this->placaInformada);
        if ($placa !== '' && ! preg_match(self::PLACA_REGEX, $placa)) {
            throw ValidationException::withMessages(['placaInformada' => 'Placa inválida. Use o formato ABC1234 ou ABC1D23.']);
        }

        return DB::transaction(function () use ($resultado, $motivo, $placa): HistoricoAcesso {
            $captura = $this->capturaPlacaId === null ? null : CapturaPlaca::query()
                ->where('operador_id', Auth::id())
                ->lockForUpdate()
                ->find($this->capturaPlacaId);
            if ($captura?->historico_acesso_id !== null) {
                throw ValidationException::withMessages(['placaInformada' => 'Esta imagem já foi usada em outro atendimento. Capture de novo.']);
            }

            $historico = $this->criarHistorico($resultado, $motivo, $placa, $captura);

            // A evidência fica ligada à decisão e não muda mais (CapturaPlaca::booted).
            $captura?->forceFill(['historico_acesso_id' => $historico->id, 'vinculada_em' => now()])->save();

            return $historico;
        });
    }

    private function criarHistorico(string $resultado, ?string $motivo, string $placa, ?CapturaPlaca $captura): HistoricoAcesso
    {
        $pessoa = $this->currentPessoaId ? Pessoa::query()->find($this->currentPessoaId) : null;
        $vinculo = $pessoa ? Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->first() : null;

        return HistoricoAcesso::query()->create([
            'placa_informada' => $placa !== '' ? $placa : null,
            'placa_origem' => $placa === '' ? null : ($captura !== null ? 'conferida' : 'manual'),
            'captura_placa_id' => $captura?->id,
            'pessoa_id' => $pessoa?->id,
            'imovel_id' => $vinculo?->imovel_id,
            'ponto_acesso' => self::PONTO_ACESSO,
            'tipo' => 'entrada',
            'resultado' => $resultado,
            'motivo_negacao' => $motivo,
            'operator_id' => Auth::id(),
            'protocol' => $this->generateProtocol(),
            'notes' => $this->notes !== '' ? $this->notes : null,
            'occurred_at' => now(),
        ]);
    }

    private function generateProtocol(): string
    {
        return 'SRA-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    /** @return list<string> */
    private function denialReasonCodigos(): array
    {
        return collect($this->denialReasonOptions())->pluck('codigo')->all();
    }

    private function denialReasonLabel(): string
    {
        return collect($this->denialReasonOptions())
            ->firstWhere('codigo', $this->denialReason)['rotulo'] ?? 'Sem autorização';
    }

    /** @return list<array{codigo: string, rotulo: string}> */
    #[Computed]
    public function denialReasonOptions(): array
    {
        $catalogo = Catalogo::porChave('motivos_negativa');

        if (! $catalogo) {
            return [];
        }

        return $catalogo->itensAtivos()
            ->map(fn (CatalogoItem $item) => ['codigo' => $item->codigo, 'rotulo' => $item->rotulo])
            ->values()
            ->all();
    }

    private function validateCommonFields(): void
    {
        if (! $this->contribuiNesteAtendimento()) {
            $this->validate(['notes' => ['nullable', 'string', 'max:200']]);

            return;
        }

        $this->validate([
            'contribution' => ['required', Rule::in(['yes', 'no', 'exempt'])],
            'paymentMethod' => ['required_if:contribution,yes', Rule::in(['dinheiro', 'pix', 'cartao'])],
            'ticketNumero' => ['required_if:contribution,yes', 'nullable', 'string', 'max:30', 'regex:'.ContribuicoesAtendimento::TICKET_REGEX],
            'notes' => ['nullable', 'string', 'max:200'],
        ], [
            'ticketNumero.required_if' => 'Informe o número do ticket de acesso para contribuição paga.',
            'ticketNumero.regex' => 'Use até 30 letras, números, ponto, barra ou hífen.',
        ]);

        if ($this->ticketDuplicado !== null && ! $this->ticketDuplicadoConfirmado) {
            throw ValidationException::withMessages([
                'ticketNumero' => 'Este ticket já foi registrado em outro atendimento. Confira e confirme para continuar.',
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.access-validation', [
            'currentPerson' => $this->currentPerson(),
            'perfilAtual' => $this->perfilAtual(),
            'denialReasonOptions' => $this->denialReasonOptions(),
        ])->layout('components.layouts.app', [
            'title' => 'Validação de entrada',
            'heading' => 'Validação de entrada',
            'headingDescription' => 'Confira os dados e decida sobre este acesso',
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Livewire\Concerns\CapturaDocumentoAssistida;
use App\Models\Catalogo;
use App\Models\CatalogoItem;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\PreRegistrationArquivo;
use App\Models\VeiculoVinculo;
use App\Models\Vinculo;
use App\Services\AuditService;
use App\Services\BuscaPessoasValidacao;
use App\Services\FotoPessoaResolver;
use App\Support\NormalizadorBusca;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AccessValidation extends Component
{
    use CapturaDocumentoAssistida;

    private const PONTO_ACESSO = 'Portaria Principal';

    public string $contribution = 'yes';

    public string $paymentMethod = 'dinheiro';

    public string $notes = '';

    public string $denialReason = 'sem_autorizacao';

    public string $denialDetails = '';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public ?string $protocol = null;

    public bool $documentRevealed = false;

    public bool $quickRegistrationOpen = false;

    public bool $quickPersonRegistered = false;

    public bool $quickDuplicateFound = false;

    public string $quickName = '';

    public string $quickDocument = '';

    public string $quickPhone = '';

    public string $quickAccessType = 'visitante';

    public string $quickResponsible = '';

    public string $quickPropertyCode = '';

    public string $quickNotes = '';

    /**
     * Pessoa do atendimento. Só muda pelos métodos do servidor (seleção na
     * busca ou cadastro rápido); nunca é escolhida automaticamente (AJ-006).
     */
    #[Locked]
    public ?string $currentPessoaId = null;

    /** Termo da busca de "Identificação da pessoa" (nome, documento, telefone, protocolo ou imóvel). */
    public string $busca = '';

    public function selecionarPessoa(string $pessoaId, AuditService $audit): void
    {
        abort_unless(Auth::user()?->hasPermission('validacao.registrar'), 403);

        // O escopo da implantação ativa vale também aqui: id de outra implantação não existe.
        $pessoa = Pessoa::query()->find($pessoaId);
        abort_if($pessoa === null, 404);

        $this->currentPessoaId = $pessoa->id;
        $this->quickPersonRegistered = false;
        $this->documentRevealed = false;
        $this->busca = '';
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
        $this->currentPessoaId = null;
        $this->quickPersonRegistered = false;
        $this->documentRevealed = false;
        unset($this->currentPerson, $this->currentProtectedFiles);
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

    public function openQuickRegistration(): void
    {
        // O que o operador já digitou na busca vira ponto de partida do cadastro.
        $termo = trim($this->busca);
        if ($this->quickName === '' && preg_match('/^[\p{L} \'.-]{3,}$/u', $termo)) {
            $this->quickName = $termo;
        } elseif ($this->quickDocument === '' && strlen(NormalizadorBusca::digitos($termo)) === 11) {
            $this->quickDocument = $termo;
        }

        $this->quickRegistrationOpen = true;
        $this->quickDuplicateFound = false;
        $this->feedback = null;
        $this->protocol = null;
        $this->resetErrorBag();
    }

    public function cancelQuickRegistration(): void
    {
        $this->quickRegistrationOpen = false;
        $this->quickDuplicateFound = false;
        $this->resetQuickRegistrationFields();
        $this->resetErrorBag();
    }

    public function checkQuickDocument(): void
    {
        $normalizado = $this->normalizeDocument($this->quickDocument);

        $this->quickDuplicateFound = $normalizado !== '' && PessoaDocumento::query()
            ->where('tipo', 'cpf')
            ->where('valor_normalizado', $normalizado)
            ->exists();

        if ($this->quickDuplicateFound) {
            $this->addError('quickDocument', 'Esta pessoa já possui cadastro. Localize o registro existente para evitar duplicidade.');
        } else {
            $this->resetErrorBag('quickDocument');
        }
    }

    public function saveQuickRegistration(): void
    {
        $this->checkQuickDocument();

        if ($this->quickDuplicateFound) {
            return;
        }

        // Dados sugeridos pelo OCR com baixa confiança exigem conferência humana.
        if ($this->camposConferencia !== []) {
            foreach ($this->camposConferencia as $campo) {
                $this->addError($campo, 'Requer conferência: confirme ou corrija este campo.');
            }

            return;
        }

        $this->validate([
            'quickName' => ['required', 'string', 'min:3', 'max:120'],
            'quickDocument' => ['required', 'string', 'min:11', 'max:20'],
            'quickPhone' => ['required', 'string', 'min:8', 'max:20'],
            'quickAccessType' => ['required', Rule::in(['visitante', 'prestador', 'morador', 'outro'])],
            'quickResponsible' => ['required_if:quickAccessType,visitante,prestador', 'nullable', 'string', 'max:120'],
            'quickPropertyCode' => ['required', 'string', 'max:40'],
            'quickNotes' => ['nullable', 'string', 'max:200'],
            'quickBirthDate' => ['nullable', 'date', 'before:today'],
        ], [
            'required' => 'Preencha este campo para continuar.',
            'required_if' => 'Preencha este campo para o tipo de acesso selecionado.',
            'min' => 'Informe um valor válido.',
        ]);

        $imovel = Imovel::query()->where('codigo', strtoupper($this->quickPropertyCode))->first();

        if (! $imovel) {
            $this->addError('quickPropertyCode', 'Imóvel não encontrado com este código.');

            return;
        }

        $pessoa = Pessoa::query()->create([
            'nome' => $this->quickName,
            'data_nascimento' => $this->quickBirthDate !== '' ? $this->quickBirthDate : null,
            'status' => 'ativo',
            'versao' => 1,
        ]);

        PessoaDocumento::query()->create([
            'pessoa_id' => $pessoa->id,
            'tipo' => 'cpf',
            'pais' => 'BR',
            'valor_normalizado' => $this->normalizeDocument($this->quickDocument),
            'valor_apresentacao' => $this->quickDocument,
            'status' => 'ativo',
            'started_at' => now(),
        ]);

        PessoaContato::query()->create([
            'pessoa_id' => $pessoa->id,
            'tipo' => 'telefone',
            'valor' => $this->quickPhone,
            'principal' => true,
            'verificado' => false,
            'started_at' => now(),
        ]);

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

        $this->currentPessoaId = $pessoa->id;
        $this->quickPersonRegistered = true;
        $this->quickRegistrationOpen = false;
        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Cadastro rápido anexado ao atendimento',
            'message' => 'Os dados mínimos foram registrados. O cadastro ainda não autoriza a entrada e deve ser complementado depois.',
        ];
    }

    public function deny(): void
    {
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
        $this->validateCommonFields();

        $historico = $this->recordHistorico('pendente');

        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Atendimento salvo sem liberação',
            'message' => 'Os dados foram preservados para continuação posterior. Nenhum comando foi enviado.',
        ];
        $this->protocol = $historico->protocol;
    }

    public function release(): void
    {
        $this->validateCommonFields();

        if ($this->quickPersonRegistered) {
            $historico = $this->recordHistorico('pendente');
            $this->feedback = [
                'variant' => 'warning',
                'title' => 'Liberação não realizada',
                'message' => 'O cadastro rápido não possui autorização válida. Salve o atendimento ou confirme a autorização por outro fluxo.',
            ];
            $this->protocol = $historico->protocol;

            return;
        }

        $historico = $this->recordHistorico('liberado');

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Validação concluída',
            'message' => 'O registro foi salvo. Nenhum portão ou equipamento real foi acionado.',
        ];
        $this->protocol = $historico->protocol;
    }

    public function startNewValidation(): void
    {
        $this->reset([
            'notes', 'denialDetails', 'feedback', 'protocol', 'quickRegistrationOpen', 'quickPersonRegistered',
            'quickDuplicateFound', 'quickName', 'quickDocument', 'quickPhone', 'quickAccessType',
            'quickResponsible', 'quickPropertyCode', 'quickNotes',
        ]);
        $this->contribution = 'yes';
        $this->paymentMethod = 'dinheiro';
        $this->denialReason = 'sem_autorizacao';
        $this->currentPessoaId = null;
        $this->busca = '';
        $this->documentRevealed = false;
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
                'links' => [],
                'vehicles' => [],
                'alerts' => [],
                'checks' => [],
            ];
        }

        $vinculos = Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->with('imovel')->orderBy('started_at')->get();
        $vinculo = $vinculos->first();
        $documento = $pessoa->documentos->first()?->valor_apresentacao;
        $foto = $this->canViewImage() ? app(FotoPessoaResolver::class)->paraPessoa($pessoa) : null;
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
        if ($foto === null && $this->canViewImage()) {
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

    private function recordHistorico(string $resultado, ?string $motivo = null): HistoricoAcesso
    {
        $pessoa = $this->currentPessoaId ? Pessoa::query()->find($this->currentPessoaId) : null;
        $vinculo = $pessoa ? Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->first() : null;

        return HistoricoAcesso::query()->create([
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
        $this->validate([
            'contribution' => ['required', Rule::in(['yes', 'no', 'exempt'])],
            'paymentMethod' => ['required_if:contribution,yes', Rule::in(['dinheiro', 'pix', 'cartao'])],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);
    }

    private function resetQuickRegistrationFields(): void
    {
        $this->quickName = '';
        $this->quickDocument = '';
        $this->quickPhone = '';
        $this->quickAccessType = 'visitante';
        $this->quickResponsible = '';
        $this->quickPropertyCode = '';
        $this->quickNotes = '';
        $this->quickBirthDate = '';
        $this->digitarManualmente();
    }

    private function normalizeDocument(string $document): string
    {
        return preg_replace('/\D/', '', $document) ?? '';
    }

    public function render(): View
    {
        return view('livewire.access-validation', [
            'currentPerson' => $this->currentPerson(),
            'denialReasonOptions' => $this->denialReasonOptions(),
        ])->layout('components.layouts.app', [
            'title' => 'Validação de entrada',
            'heading' => 'Validação de entrada',
            'headingDescription' => 'Confira os dados e decida sobre este acesso',
        ]);
    }
}

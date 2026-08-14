<?php

namespace App\Livewire;

use App\Models\Catalogo;
use App\Models\CatalogoItem;
use App\Models\HistoricoAcesso;
use App\Models\Imovel;
use App\Models\Pessoa;
use App\Models\PessoaContato;
use App\Models\PessoaDocumento;
use App\Models\PreRegistration;
use App\Models\PreRegistrationArquivo;
use App\Models\Vinculo;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AccessValidation extends Component
{
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

    #[Locked]
    public ?string $currentPessoaId = null;

    public function mount(): void
    {
        $this->currentPessoaId = $this->findResidentWithActiveVinculo();
    }

    public function openQuickRegistration(): void
    {
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

        $this->validate([
            'quickName' => ['required', 'string', 'min:3', 'max:120'],
            'quickDocument' => ['required', 'string', 'min:11', 'max:20'],
            'quickPhone' => ['required', 'string', 'min:8', 'max:20'],
            'quickAccessType' => ['required', Rule::in(['visitante', 'prestador', 'morador', 'outro'])],
            'quickResponsible' => ['required_if:quickAccessType,visitante,prestador', 'nullable', 'string', 'max:120'],
            'quickPropertyCode' => ['required', 'string', 'max:40'],
            'quickNotes' => ['nullable', 'string', 'max:200'],
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
        $this->currentPessoaId = $this->findResidentWithActiveVinculo();
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

    /** @return array{name: string, initials: string, document: string, type: string, property: string, responsible: string, status: string, validity: string} */
    #[Computed]
    public function currentPerson(): array
    {
        $pessoa = $this->currentPessoaId ? Pessoa::query()->with('documentos')->find($this->currentPessoaId) : null;

        if (! $pessoa) {
            return [
                'name' => 'Nenhuma pessoa identificada',
                'initials' => '—',
                'document' => 'Não informado',
                'type' => 'Não identificado',
                'property' => 'Não vinculado',
                'responsible' => 'A confirmar',
                'status' => 'Sem cadastro',
                'validity' => '—',
            ];
        }

        $vinculo = Vinculo::query()->where('pessoa_id', $pessoa->id)->whereNull('ended_at')->with('imovel')->first();

        return [
            'name' => $pessoa->nomeExibicao(),
            'initials' => $this->initialsFromName($pessoa->nome),
            'document' => $this->documentRevealed
                ? ($pessoa->documentos->first()?->valor_apresentacao ?? 'Não informado')
                : $this->maskDocument($pessoa->documentos->first()?->valor_apresentacao),
            'type' => $this->relationLabel($vinculo?->tipo),
            'property' => $vinculo?->imovel?->label() ?? 'Não vinculado',
            'responsible' => $this->quickPersonRegistered && $this->quickResponsible !== '' ? $this->quickResponsible : 'Próprio morador',
            'status' => $this->quickPersonRegistered ? 'Cadastro mínimo' : 'Cadastro ativo',
            'validity' => $this->quickPersonRegistered ? 'Aguardando análise' : 'Acesso permanente',
        ];
    }

    private function maskDocument(?string $document): string
    {
        $digits = preg_replace('/\D/', '', (string) $document) ?? '';

        return strlen($digits) === 11
            ? '***.***.'.substr($digits, 6, 3).'-**'
            : ($document ? 'Documento protegido' : 'Não informado');
    }

    /** @return array{document: ?PreRegistrationArquivo, selfie: ?PreRegistrationArquivo, pre_registration_id: ?string} */
    #[Computed]
    public function currentProtectedFiles(): array
    {
        if ($this->quickPersonRegistered || ! $this->currentPessoaId) {
            return ['document' => null, 'selfie' => null, 'pre_registration_id' => null];
        }

        $presentedDocument = PessoaDocumento::query()
            ->where('pessoa_id', $this->currentPessoaId)
            ->where('status', 'ativo')
            ->whereNull('ended_at')
            ->value('valor_apresentacao');

        if (! $presentedDocument) {
            return ['document' => null, 'selfie' => null, 'pre_registration_id' => null];
        }

        $preRegistration = PreRegistration::query()
            ->where('document', $presentedDocument)
            ->where('status', 'aprovado')
            ->with('fileLinks.file')
            ->latest('submitted_at')
            ->first();

        return [
            'document' => $preRegistration?->currentFileLink('documento'),
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

    private function findResidentWithActiveVinculo(): ?string
    {
        return Vinculo::query()->whereNull('ended_at')->value('pessoa_id');
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
    }

    private function normalizeDocument(string $document): string
    {
        return preg_replace('/\D/', '', $document) ?? '';
    }

    private function relationLabel(?string $tipo): string
    {
        return match ($tipo) {
            'proprietario' => 'Proprietário',
            'inquilino' => 'Inquilino',
            'morador' => 'Morador',
            'prestador' => 'Prestador',
            'visitante' => 'Visitante',
            default => 'Outro acesso',
        };
    }

    private function initialsFromName(string $name): string
    {
        return collect(explode(' ', trim($name)))
            ->filter(fn (string $part): bool => ! in_array(mb_strtolower($part), ['da', 'de', 'do', 'das', 'dos'], true))
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->join('');
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

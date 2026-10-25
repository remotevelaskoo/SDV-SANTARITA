<?php

namespace App\Livewire;

use App\Exceptions\StalePreRegistrationException;
use App\Models\PreRegistration;
use App\Models\PreRegistrationEdit;
use App\Services\AuditService;
use App\Support\ComparacaoOcrPreCadastro;
use App\Support\DestinationDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Análise de um pré-cadastro em página própria (AJ-006, fatia 3).
 *
 * Substitui o painel lateral estreito da fila: imagens em tamanho de
 * conferência, todos os dados, comparação com a leitura automática,
 * histórico e as decisões, cada uma conferida no backend pela permissão
 * específica e pela situação atual do registro.
 */
class PreRegistrationReview extends Component
{
    #[Locked]
    public string $preRegistrationId;

    public string $rejectionReason = 'documento_incompleto';

    public string $correctionItems = 'documento';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public bool $editing = false;

    public bool $documentRevealed = false;

    public ?int $editVersion = null;

    public string $editName = '';

    public string $editDocument = '';

    public string $editBirthDate = '';

    public string $editPhone = '';

    public string $editEmail = '';

    public string $editAddressInformed = '';

    public string $editDestinationProperty = '';

    public string $editDestinationLabel = '';

    public string $editResponsibleName = '';

    public string $editPeriodStart = '';

    public string $editPeriodEnd = '';

    public string $editVehiclePlate = '';

    public string $editVehicleModel = '';

    public string $editVehicleColor = '';

    public string $editReason = '';

    public const REJECTION_REASONS = [
        'documento_incompleto' => 'Documento incompleto',
        'dados_divergentes' => 'Dados divergentes',
        'periodo_invalido' => 'Período inválido',
        'solicitacao_nao_confirmada' => 'Solicitação não confirmada',
    ];

    public const CORRECTION_ITEMS = [
        'dados_pessoais' => 'Dados pessoais',
        'documento' => 'Documento',
        'selfie' => 'Selfie',
        'veiculo' => 'Veículo',
    ];

    /** @var array<string, array{column: string, label: string}> */
    private const EDITABLE_FIELDS = [
        'editName' => ['column' => 'name', 'label' => 'Nome completo'],
        'editDocument' => ['column' => 'document', 'label' => 'Documento'],
        'editPhone' => ['column' => 'phone', 'label' => 'Telefone'],
        'editEmail' => ['column' => 'email', 'label' => 'E-mail'],
        'editAddressInformed' => ['column' => 'address_informed', 'label' => 'Endereço informado'],
        'editVehiclePlate' => ['column' => 'vehicle_plate', 'label' => 'Placa do veículo'],
        'editVehicleModel' => ['column' => 'vehicle_model', 'label' => 'Modelo do veículo'],
        'editVehicleColor' => ['column' => 'vehicle_color', 'label' => 'Cor do veículo'],
    ];

    public function mount(string $preRegistration): void
    {
        abort_unless(Auth::user()?->hasPermission('pre-registro.analisar'), 403);

        // O escopo de implantação faz um pré-cadastro de outra implantação responder 404.
        $this->preRegistrationId = PreRegistration::query()->findOrFail($preRegistration)->id;
    }

    public function record(): PreRegistration
    {
        return PreRegistration::query()
            ->with(['edits', 'fileLinks.file', 'documentExtractions'])
            ->findOrFail($this->preRegistrationId);
    }

    public function toggleDocumentReveal(AuditService $audit): void
    {
        abort_unless(Auth::user()?->hasPermission('dados-sensiveis.revelar'), 403);

        if ($this->documentRevealed) {
            $this->documentRevealed = false;

            return;
        }

        $record = $this->record();
        $this->documentRevealed = true;

        $audit->record(
            action: 'revelou_dado_sensivel',
            module: 'pre-cadastro',
            entityType: 'pre_registrations',
            entityId: $record->id,
            classification: 'restrita',
            metadata: ['categoria' => 'documento', 'contexto' => 'analise_pre_cadastro'],
        );
    }

    public function hideSensitiveDocument(): void
    {
        $this->documentRevealed = false;
    }

    public function approve(): void
    {
        $this->authorizeDecision('pre-registro.aprovar');

        if ($this->editing) {
            $this->addError('editReason', 'Salve ou cancele a edição antes de aprovar.');

            return;
        }

        if (! $this->transitionStatus('aprovado', 'Aprovação registrada pela portaria.')) {
            return;
        }

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Pré-cadastro aprovado',
            'message' => 'A entrada ainda será validada pela portaria conforme as regras vigentes.',
        ];
    }

    public function reject(): void
    {
        $this->authorizeDecision('pre-registro.rejeitar');

        $this->validate([
            'rejectionReason' => ['required', Rule::in(array_keys(self::REJECTION_REASONS))],
        ]);

        if ($this->editing) {
            $this->addError('editReason', 'Salve ou cancele a edição antes de rejeitar.');

            return;
        }

        if (! $this->transitionStatus('rejeitado', $this->rejectionReason)) {
            return;
        }

        $this->feedback = [
            'variant' => 'danger',
            'title' => 'Pré-cadastro rejeitado',
            'message' => 'A decisão foi registrada. A observação interna não será enviada ao solicitante.',
        ];
    }

    public function requestCorrection(): void
    {
        $this->authorizeDecision('pre-registro.solicitar-correcao');

        $this->validate([
            'correctionItems' => ['required', Rule::in(array_keys(self::CORRECTION_ITEMS))],
        ]);

        if ($this->editing) {
            $this->addError('editReason', 'Salve ou cancele a edição antes de solicitar correção.');

            return;
        }

        if (! $this->transitionStatus('correcao', $this->correctionItems)) {
            return;
        }

        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Correção solicitada',
            'message' => 'O item selecionado foi enviado para correção, preservando a versão analisada.',
        ];
    }

    public function beginEdit(): void
    {
        $record = $this->record();

        Gate::authorize('edit', $record);

        $this->resetErrorBag();

        if (! $record->isEditable()) {
            $this->addError('state', 'Este pré-cadastro não está mais em análise e não pode ser editado.');

            return;
        }

        $this->editing = true;
        $this->editVersion = $record->version;
        $this->editName = $record->name;
        $this->editDocument = $record->document;
        $this->editBirthDate = $record->birth_date->format('Y-m-d');
        $this->editPhone = $record->phone;
        $this->editEmail = $record->email;
        $this->editAddressInformed = $record->address_informed;
        $this->editDestinationProperty = (string) $record->destination_property;
        $this->editDestinationLabel = $record->destination_label;
        $this->editResponsibleName = (string) $record->responsible_name;
        $this->editPeriodStart = $record->period_start->format('Y-m-d\TH:i');
        $this->editPeriodEnd = $record->period_end->format('Y-m-d\TH:i');
        $this->editVehiclePlate = (string) $record->vehicle_plate;
        $this->editVehicleModel = (string) $record->vehicle_model;
        $this->editVehicleColor = (string) $record->vehicle_color;
        $this->editReason = '';
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->editVersion = null;
        $this->editReason = '';
        $this->resetErrorBag();
    }

    public function saveEdit(): void
    {
        if (! $this->editing) {
            return;
        }

        $record = $this->record();

        Gate::authorize('edit', $record);

        if (! $record->isEditable()) {
            $this->addError('state', 'Este pré-cadastro não está mais em análise e não pode ser editado.');
            $this->editing = false;

            return;
        }

        $rules = [
            'editName' => ['required', 'string', 'min:3', 'max:120'],
            'editDocument' => ['required', 'string', 'max:20'],
            'editBirthDate' => ['required', 'date', 'before:today'],
            'editPhone' => ['required', 'string', 'min:8', 'max:20'],
            'editEmail' => ['required', 'email', 'max:120'],
            'editAddressInformed' => ['required', 'string', 'max:200'],
            'editPeriodStart' => ['required', 'date'],
            'editPeriodEnd' => ['required', 'date', 'after:editPeriodStart'],
            'editVehiclePlate' => ['nullable', 'string', 'max:10'],
            'editVehicleModel' => ['nullable', 'string', 'max:60'],
            'editVehicleColor' => ['nullable', 'string', 'max:30'],
            'editReason' => ['required', 'string', 'min:5', 'max:200'],
        ];

        if ($record->access_type === 'visitante') {
            $rules['editDestinationProperty'] = ['required', Rule::in(DestinationDirectory::options())];
        } elseif ($record->access_type === 'prestador') {
            $rules['editDestinationLabel'] = ['required', 'string', 'max:120'];
            $rules['editResponsibleName'] = ['required', 'string', 'max:120'];
        }

        $this->validate($rules, [
            'required' => 'Preencha este campo antes de salvar.',
            'email' => 'Informe um e-mail válido.',
            'after' => 'O término deve ser depois do início.',
        ]);

        try {
            DB::transaction(function () {
                $fresh = PreRegistration::query()->whereKey($this->preRegistrationId)->lockForUpdate()->firstOrFail();

                if ($fresh->version !== $this->editVersion) {
                    throw new StalePreRegistrationException;
                }

                $this->applyEdit($fresh);
            });
        } catch (StalePreRegistrationException $exception) {
            $this->addError('editReason', $exception->getMessage().' Reabra a edição para carregar os dados atuais.');
            $this->editing = false;
            $this->editVersion = null;

            return;
        }

        $this->editing = false;
        $this->editVersion = null;
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Correção salva com auditoria',
            'message' => 'A versão original foi preservada e as alterações estão prontas para nova conferência antes da aprovação.',
        ];
    }

    private function authorizeDecision(string $permission): void
    {
        abort_unless(Auth::user()?->hasPermission($permission), 403);
    }

    private function applyEdit(PreRegistration $record): void
    {
        $operator = Auth::user();
        $occurredAt = now();
        $changes = [];

        foreach (self::EDITABLE_FIELDS as $property => $meta) {
            $old = (string) $record->{$meta['column']};
            $new = (string) $this->{$property};

            if ($old !== $new) {
                $changes[] = [$meta['column'], $meta['label'], $old, $new];
            }

            $record->{$meta['column']} = $this->{$property};
        }

        if ($record->access_type === 'visitante') {
            $responsible = DestinationDirectory::responsibleFor($this->editDestinationProperty);

            $this->diffAndQueue($changes, 'destination_property', 'Imóvel de destino', $record->destination_property, $this->editDestinationProperty);
            $record->destination_property = $this->editDestinationProperty;
            $record->destination_label = $this->editDestinationProperty;
            $record->responsible_name = $responsible;
        } elseif ($record->access_type === 'prestador') {
            $this->diffAndQueue($changes, 'destination_label', 'Destino', $record->destination_label, $this->editDestinationLabel);
            $this->diffAndQueue($changes, 'responsible_name', 'Responsável', $record->responsible_name, $this->editResponsibleName);
            $record->destination_label = $this->editDestinationLabel;
            $record->responsible_name = $this->editResponsibleName;
        }

        $newStart = Carbon::parse($this->editPeriodStart);
        $newEnd = Carbon::parse($this->editPeriodEnd);

        if (! $record->period_start->equalTo($newStart)) {
            $changes[] = ['period_start', 'Início do período', $record->period_start->format('d/m/Y H:i'), $newStart->format('d/m/Y H:i')];
        }
        if (! $record->period_end->equalTo($newEnd)) {
            $changes[] = ['period_end', 'Término do período', $record->period_end->format('d/m/Y H:i'), $newEnd->format('d/m/Y H:i')];
        }
        $record->period_start = $newStart;
        $record->period_end = $newEnd;

        if (! $record->birth_date->equalTo($this->editBirthDate)) {
            $changes[] = ['birth_date', 'Data de nascimento', $record->birth_date->format('d/m/Y'), Carbon::parse($this->editBirthDate)->format('d/m/Y')];
        }
        $record->birth_date = $this->editBirthDate;

        $record->version = $record->version + 1;
        $record->save();

        foreach ($changes as [$column, $label, $old, $new]) {
            PreRegistrationEdit::query()->create([
                'pre_registration_id' => $record->id,
                'action' => 'campo_corrigido',
                'field' => $label,
                'old_value' => $old,
                'new_value' => $new,
                'reason' => $this->editReason,
                'result' => 'sucesso',
                'operator_id' => $operator?->id,
                'operator_name' => $operator?->name ?? 'Operador não identificado',
                'occurred_at' => $occurredAt,
            ]);
        }

        if ($changes === []) {
            PreRegistrationEdit::query()->create([
                'pre_registration_id' => $record->id,
                'action' => 'revisao_sem_alteracao',
                'field' => 'Revisão',
                'old_value' => null,
                'new_value' => null,
                'reason' => $this->editReason,
                'result' => 'sucesso',
                'operator_id' => $operator?->id,
                'operator_name' => $operator?->name ?? 'Operador não identificado',
                'occurred_at' => $occurredAt,
            ]);
        }
    }

    /** @param list<array{0: string, 1: string, 2: string|null, 3: string|null}> $changes */
    private function diffAndQueue(array &$changes, string $column, string $label, ?string $old, ?string $new): void
    {
        if ((string) $old !== (string) $new) {
            $changes[] = [$column, $label, $old, $new];
        }
    }

    /**
     * Uma decisão só sai de "aguardando": um registro já decidido mostra a
     * decisão e não aceita outra por esta tela, mesmo que a requisição chegue
     * de uma aba desatualizada.
     */
    private function transitionStatus(string $status, string $reason): bool
    {
        return DB::transaction(function () use ($status, $reason) {
            $record = PreRegistration::query()->whereKey($this->preRegistrationId)->lockForUpdate()->firstOrFail();

            if (! $record->isEditable()) {
                $this->addError('state', 'Este pré-cadastro já tem uma decisão registrada. Atualize a página para ver a situação atual.');

                return false;
            }

            $old = $record->status;

            $record->status = $status;
            $record->status_changed_at = now();
            $record->version = $record->version + 1;
            $record->save();

            $operator = Auth::user();

            PreRegistrationEdit::query()->create([
                'pre_registration_id' => $record->id,
                'action' => 'situacao_alterada',
                'field' => 'Situação',
                'old_value' => $old,
                'new_value' => $status,
                'reason' => $reason,
                'result' => 'sucesso',
                'operator_id' => $operator?->id,
                'operator_name' => $operator?->name ?? 'Portaria',
                'occurred_at' => now(),
            ]);

            return true;
        });
    }

    /** @return list<array{variant: string, title: string, message: string}> */
    private function alerts(PreRegistration $record, int $divergencias): array
    {
        $alerts = [];

        if ($record->alert) {
            $alerts[] = ['variant' => 'warning', 'title' => 'Atenção', 'message' => $record->alert];
        }
        if (! $record->currentFileLink('selfie')?->file) {
            $alerts[] = ['variant' => 'warning', 'title' => 'Sem selfie', 'message' => 'Confira a identidade presencialmente na portaria.'];
        }
        if (! $record->currentFileLink('documento')?->file) {
            $alerts[] = ['variant' => 'info', 'title' => 'Documento não enviado', 'message' => 'O documento deverá ser conferido na portaria.'];
        }
        if ($divergencias > 0) {
            $alerts[] = ['variant' => 'warning', 'title' => $divergencias === 1 ? '1 campo para conferir' : "{$divergencias} campos para conferir", 'message' => 'A leitura automática e os dados confirmados não coincidem ou têm confiança baixa. Compare com a imagem do documento.'];
        }
        if ($record->period_end->isPast()) {
            $alerts[] = ['variant' => 'warning', 'title' => 'Período encerrado', 'message' => 'O período informado já terminou.'];
        }

        return $alerts;
    }

    public function render(): View
    {
        $record = $this->record();
        $user = Auth::user();
        $leitura = $record->documentExtractions->first();
        $linhasOcr = $leitura ? ComparacaoOcrPreCadastro::linhas($record, $leitura) : [];
        $divergencias = ComparacaoOcrPreCadastro::divergencias($linhasOcr);

        $imagens = [];
        foreach (['selfie' => 'Selfie', 'documento_foto' => 'Foto recortada do documento', 'documento' => 'Documento (frente)', 'documento_verso' => 'Documento (verso)'] as $categoria => $rotulo) {
            $link = $record->currentFileLink($categoria);
            $imagens[] = [
                'categoria' => $categoria,
                'rotulo' => $rotulo,
                'arquivo' => $link?->file,
                'url' => $link?->file?->isAvailable()
                    ? route('protected-files.show', ['arquivo' => $link->file, 'contexto' => 'analise_pre_cadastro'])
                    : null,
            ];
        }

        return view('livewire.pre-registration-review', [
            'record' => $record,
            'leitura' => $leitura,
            'linhasOcr' => $linhasOcr,
            'alerts' => $this->alerts($record, $divergencias),
            'imagens' => $imagens,
            'podeVerImagens' => (bool) $user?->hasPermission('arquivos.sensiveis.visualizar'),
            'podeRevelar' => (bool) $user?->hasPermission('dados-sensiveis.revelar'),
            'pode' => [
                'editar' => (bool) $user?->hasPermission('pre-registro.editar'),
                'aprovar' => (bool) $user?->hasPermission('pre-registro.aprovar'),
                'rejeitar' => (bool) $user?->hasPermission('pre-registro.rejeitar'),
                'corrigir' => (bool) $user?->hasPermission('pre-registro.solicitar-correcao'),
            ],
        ])->layout('components.layouts.app', [
            'title' => 'Análise do pré-cadastro',
            'heading' => 'Análise do pré-cadastro',
            'headingDescription' => $record->protocol,
        ]);
    }
}

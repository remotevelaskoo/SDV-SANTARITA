<?php

namespace App\Livewire;

use App\Exceptions\StalePreRegistrationException;
use App\Models\PreRegistration;
use App\Models\PreRegistrationEdit;
use App\Support\DestinationDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PreRegistrationQueue extends Component
{
    public string $statusFilter = 'aguardando';

    public string $search = '';

    public string $rejectionReason = 'documento_incompleto';

    public string $correctionItems = 'documento';

    /** @var array{variant: string, title: string, message: string}|null */
    public ?array $feedback = null;

    public ?string $editingId = null;

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

    public function setStatusFilter(string $status): void
    {
        if (in_array($status, ['aguardando', 'aprovado', 'rejeitado', 'todos'], true)) {
            $this->statusFilter = $status;
        }
    }

    public function approve(string $id): void
    {
        if ($this->editingId === $id) {
            $this->addError('editReason', 'Salve ou cancele a edição antes de aprovar.');

            return;
        }

        $this->transitionStatus($id, 'aprovado', 'Aprovação registrada pela portaria.');

        $this->feedback = [
            'variant' => 'success',
            'title' => 'Pré-cadastro aprovado',
            'message' => 'A entrada ainda será validada pela portaria conforme as regras vigentes.',
        ];
    }

    public function reject(string $id): void
    {
        $this->validate([
            'rejectionReason' => ['required', Rule::in(['documento_incompleto', 'dados_divergentes', 'periodo_invalido', 'solicitacao_nao_confirmada'])],
        ]);

        if ($this->editingId === $id) {
            $this->addError('editReason', 'Salve ou cancele a edição antes de rejeitar.');

            return;
        }

        $this->transitionStatus($id, 'rejeitado', $this->rejectionReason);

        $this->feedback = [
            'variant' => 'danger',
            'title' => 'Pré-cadastro rejeitado',
            'message' => 'A decisão foi registrada. A observação interna não será enviada ao solicitante.',
        ];
    }

    public function requestCorrection(string $id): void
    {
        $this->validate([
            'correctionItems' => ['required', Rule::in(['dados_pessoais', 'documento', 'selfie', 'veiculo'])],
        ]);

        if ($this->editingId === $id) {
            $this->addError('editReason', 'Salve ou cancele a edição antes de solicitar correção.');

            return;
        }

        $this->transitionStatus($id, 'correcao', $this->correctionItems);

        $this->feedback = [
            'variant' => 'warning',
            'title' => 'Correção solicitada',
            'message' => 'O item selecionado foi enviado para correção, preservando a versão analisada.',
        ];
    }

    public function beginEdit(string $id): void
    {
        $record = PreRegistration::findOrFail($id);

        Gate::authorize('edit', $record);

        // O <dialog> nativo perde o estado "aberto" a cada remorph do Livewire.
        // Reabrir aqui (e não em dehydrate(), que roda depois de SupportEvents já
        // ter drenado os eventos despachados para a resposta) garante que o
        // operador continue vendo o painel, inclusive quando o erro de estado abaixo é exibido.
        $this->dispatch("pre-registration-edit-started-{$id}");

        $this->resetErrorBag();

        if (! $record->isEditable()) {
            $this->addError('state', 'Este pré-cadastro não está mais em análise e não pode ser editado.');

            return;
        }

        $this->editingId = $record->id;
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
        $this->editingId = null;
        $this->editVersion = null;
        $this->editReason = '';
        $this->resetErrorBag();
    }

    public function saveEdit(string $id): void
    {
        if ($this->editingId !== $id) {
            return;
        }

        $record = PreRegistration::findOrFail($id);

        Gate::authorize('edit', $record);

        // Despachado antes da validação para que o painel reabra mesmo quando
        // $this->validate() lançar e interromper o resto do método (ver beginEdit()).
        $this->dispatch("pre-registration-edit-started-{$id}");

        if (! $record->isEditable()) {
            $this->addError('state', 'Este pré-cadastro não está mais em análise e não pode ser editado.');
            $this->editingId = null;

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
            DB::transaction(function () use ($id) {
                $fresh = PreRegistration::query()->whereKey($id)->lockForUpdate()->firstOrFail();

                if ($fresh->version !== $this->editVersion) {
                    throw new StalePreRegistrationException;
                }

                $this->applyEdit($fresh);
            });
        } catch (StalePreRegistrationException $exception) {
            $this->addError('editReason', $exception->getMessage().' Reabra a edição para carregar os dados atuais.');
            $this->editingId = null;
            $this->editVersion = null;

            return;
        }

        $this->editingId = null;
        $this->editVersion = null;
        $this->feedback = [
            'variant' => 'success',
            'title' => 'Correção salva com auditoria',
            'message' => 'A versão original foi preservada e as alterações estão prontas para nova conferência antes da aprovação.',
        ];
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

    private function transitionStatus(string $id, string $status, string $reason): void
    {
        DB::transaction(function () use ($id, $status, $reason) {
            $record = PreRegistration::query()->whereKey($id)->lockForUpdate()->firstOrFail();
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
        });
    }

    /** @return Builder<PreRegistration> */
    private function baseQuery(): Builder
    {
        return PreRegistration::query()
            ->with('edits')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('protocol', 'like', $term)
                        ->orWhere('vehicle_plate', 'like', $term)
                        ->orWhere('destination_label', 'like', $term);
                });
            })
            ->orderBy('submitted_at');
    }

    /** @return Collection<int, PreRegistration> */
    public function allRecords(): Collection
    {
        return $this->baseQuery()->get();
    }

    /** @return Collection<int, PreRegistration> */
    public function filteredRecords(): Collection
    {
        return $this->baseQuery()
            ->when($this->statusFilter !== 'todos', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->get();
    }

    public function render(): View
    {
        return view('livewire.pre-registration-queue', [
            'records' => $this->allRecords(),
            'filteredRecords' => $this->filteredRecords(),
        ])->layout('components.layouts.app', [
            'title' => 'Pré-cadastros',
            'heading' => 'Pré-cadastros',
            'headingDescription' => 'Solicitações antecipadas aguardando análise da portaria',
        ]);
    }
}

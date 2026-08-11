@props(['record', 'editing' => false])

<div class="pre-registration-detail">
    <header class="pre-registration-detail__person">
        <span>{{ $record->initials() }}</span>
        <div><strong>{{ $record->name }}</strong><small>{{ ucfirst($record->access_type) }} · {{ $record->document }}</small></div>
        <x-ui.badge :variant="match ($record->status) { 'aprovado' => 'success', 'rejeitado' => 'danger', 'correcao' => 'warning', default => 'info' }">
            {{ match ($record->status) { 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado', 'correcao' => 'Correção', default => 'Aguardando' } }}
        </x-ui.badge>
    </header>

    @error('state')
        <x-ui.alert variant="danger" title="Não é possível editar">{{ $message }}</x-ui.alert>
    @enderror
    @error('editReason')
        <x-ui.alert variant="danger" title="Conflito ou pendência na edição">{{ $message }}</x-ui.alert>
    @enderror

    @if ($record->alert)
        <x-ui.alert variant="warning" title="Atenção">{{ $record->alert }}</x-ui.alert>
    @endif

    <section aria-labelledby="detail-data-{{ $record->id }}">
        <h4 id="detail-data-{{ $record->id }}">Dados preenchidos</h4>
        <dl class="pre-registration-detail__data">
            <div><dt>Nome completo</dt><dd>{{ $record->name }}</dd></div>
            <div><dt>Documento</dt><dd>{{ $record->document }}</dd></div>
            <div><dt>Data de nascimento</dt><dd>{{ $record->birth_date->format('d/m/Y') }}</dd></div>
            <div><dt>Telefone</dt><dd>{{ $record->phone }}</dd></div>
            <div><dt>E-mail</dt><dd>{{ $record->email }}</dd></div>
            <div><dt>Endereço informado</dt><dd>{{ $record->address_informed }}</dd></div>
            <div><dt>Tipo de acesso</dt><dd>{{ ucfirst($record->access_type) }}</dd></div>
            <div><dt>Destino</dt><dd>{{ $record->destination_label }}</dd></div>
            @if ($record->requiresProperty())
                <div><dt>Imóvel</dt><dd>{{ $record->destination_property }}</dd></div>
            @endif
            <div><dt>Responsável</dt><dd>{{ $record->responsible_name ?? 'Não exige responsável de imóvel' }}</dd></div>
            <div><dt>Período</dt><dd>{{ $record->periodLabel() }}</dd></div>
            <div><dt>Veículo</dt><dd>{{ $record->vehicleLabel() }}</dd></div>
            <div><dt>Situação do documento</dt><dd>{{ $record->document_status }}</dd></div>
            <div><dt>Situação da selfie</dt><dd>{{ $record->selfie_status }}</dd></div>
            <div><dt>Protocolo</dt><dd>{{ $record->protocol }}</dd></div>
            <div><dt>Enviado em</dt><dd>{{ $record->submitted_at->format('d/m/Y \à\s H:i') }}</dd></div>
        </dl>
    </section>

    @if ($editing)
        <section aria-labelledby="detail-edit-{{ $record->id }}">
            <h4 id="detail-edit-{{ $record->id }}">Corrigir antes de aprovar</h4>
            <x-ui.alert variant="warning" title="Edição auditada">
                A versão enviada pelo solicitante será preservada. Informe a justificativa da correção.
            </x-ui.alert>
            <div class="registration-fields">
                <x-ui.field id="edit-name-{{ $record->id }}" label="Nome completo" wire:model="editName" :error="$errors->first('editName')" required />
                <x-ui.field id="edit-document-{{ $record->id }}" label="Documento" wire:model="editDocument" :error="$errors->first('editDocument')" required />
                <x-ui.field id="edit-birth-date-{{ $record->id }}" type="date" label="Data de nascimento" wire:model="editBirthDate" :error="$errors->first('editBirthDate')" required />
                <x-ui.field id="edit-phone-{{ $record->id }}" label="Telefone" wire:model="editPhone" :error="$errors->first('editPhone')" required />
                <x-ui.field id="edit-email-{{ $record->id }}" type="email" label="E-mail" wire:model="editEmail" :error="$errors->first('editEmail')" required />
                <x-ui.field id="edit-address-{{ $record->id }}" label="Endereço informado" wire:model="editAddressInformed" :error="$errors->first('editAddressInformed')" required />

                @if ($record->access_type === 'turista')
                    <x-ui.field id="edit-destination-{{ $record->id }}" label="Destino" value="Praia do Santa Rita" readonly help="Turista não exige imóvel nem responsável." />
                @elseif ($record->access_type === 'visitante')
                    <x-ui.select id="edit-destination-property-{{ $record->id }}" label="Imóvel de destino" wire:model="editDestinationProperty" :error="$errors->first('editDestinationProperty')" required>
                        @foreach (\App\Support\DestinationDirectory::options() as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </x-ui.select>
                @else
                    <x-ui.field id="edit-destination-label-{{ $record->id }}" label="Destino" wire:model="editDestinationLabel" :error="$errors->first('editDestinationLabel')" required />
                    <x-ui.field id="edit-responsible-{{ $record->id }}" label="Responsável" wire:model="editResponsibleName" :error="$errors->first('editResponsibleName')" required />
                @endif

                <x-ui.field id="edit-period-start-{{ $record->id }}" type="datetime-local" label="Início do período" wire:model="editPeriodStart" :error="$errors->first('editPeriodStart')" required />
                <x-ui.field id="edit-period-end-{{ $record->id }}" type="datetime-local" label="Término do período" wire:model="editPeriodEnd" :error="$errors->first('editPeriodEnd')" required />
                <x-ui.field id="edit-vehicle-plate-{{ $record->id }}" label="Placa do veículo" wire:model="editVehiclePlate" :error="$errors->first('editVehiclePlate')" help="Deixe em branco se não houver veículo." />
                <x-ui.field id="edit-vehicle-model-{{ $record->id }}" label="Modelo do veículo" wire:model="editVehicleModel" :error="$errors->first('editVehicleModel')" />
                <x-ui.field id="edit-vehicle-color-{{ $record->id }}" label="Cor do veículo" wire:model="editVehicleColor" :error="$errors->first('editVehicleColor')" />
                <x-ui.field id="edit-reason-{{ $record->id }}" label="Justificativa da correção" wire:model="editReason" help="Obrigatória para auditoria." :error="$errors->first('editReason')" required />
            </div>
            <div class="pre-registration-review-actions">
                <x-ui.button variant="secondary" wire:click="cancelEdit">Cancelar edição</x-ui.button>
                <x-ui.button variant="success" wire:click="saveEdit('{{ $record->id }}')">Salvar correção</x-ui.button>
            </div>
        </section>
    @endif

    <section aria-labelledby="detail-history-{{ $record->id }}">
        <h4 id="detail-history-{{ $record->id }}">Histórico</h4>
        <ul class="pre-registration-history">
            <li><span></span><div><strong>Pré-cadastro enviado</strong><small>{{ $record->submitted_at->format('d/m/Y \à\s H:i') }} · Fluxo público</small></div></li>
            <li><span></span><div><strong>Disponível para análise</strong><small>Fila da Portaria Principal · versão {{ $record->version }}</small></div></li>
            @foreach ($record->edits as $entry)
                @if ($entry->action === 'situacao_alterada')
                    <li><span></span><div><strong>Situação alterada para "{{ $entry->new_value }}" por {{ $entry->operator_name }}</strong><small>{{ $entry->occurred_at->format('d/m/Y \à\s H:i') }} · {{ $entry->reason }}</small></div></li>
                @elseif ($entry->action === 'revisao_sem_alteracao')
                    <li><span></span><div><strong>Dados revisados por {{ $entry->operator_name }}</strong><small>{{ $entry->occurred_at->format('d/m/Y \à\s H:i') }} · {{ $entry->reason }} · sem alteração de valor</small></div></li>
                @else
                    <li><span></span><div><strong>{{ $entry->field }} corrigido por {{ $entry->operator_name }}</strong><small>{{ $entry->occurred_at->format('d/m/Y \à\s H:i') }} · {{ $entry->reason }}</small><small>{{ $entry->old_value }} → {{ $entry->new_value }}</small></div></li>
                @endif
            @endforeach
        </ul>
    </section>

    <x-ui.alert variant="info" title="Aprovação não garante entrada">
        Aprovar este pré-cadastro apenas prepara a solicitação para a Validação de Entrada.
    </x-ui.alert>
</div>

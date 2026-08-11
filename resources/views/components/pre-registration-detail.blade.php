@props(['record', 'details', 'editing' => false, 'auditEntries' => []])

<div class="pre-registration-detail">
    <header class="pre-registration-detail__person">
        <span>{{ $record['initials'] }}</span>
        <div><strong>{{ $record['name'] }}</strong><small>{{ $record['type'] }} · {{ $record['document'] }}</small></div>
        <x-ui.badge :variant="match ($record['status']) { 'aprovado' => 'success', 'rejeitado' => 'danger', 'correcao' => 'warning', default => 'info' }">
            {{ match ($record['status']) { 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado', 'correcao' => 'Correção', default => 'Aguardando' } }}
        </x-ui.badge>
    </header>

    @if ($record['alert'])
        <x-ui.alert variant="warning" title="Atenção">{{ $record['alert'] }}</x-ui.alert>
    @endif

    <section aria-labelledby="detail-checklist-{{ $record['id'] }}">
        <h4 id="detail-checklist-{{ $record['id'] }}">Checklist da análise</h4>
        <ul class="pre-registration-checklist">
            <li><x-icon name="check-circle" /><span><strong>Dados pessoais</strong><small>Campos obrigatórios preenchidos</small></span></li>
            <li><x-icon name="check-circle" /><span><strong>Documento</strong><small>Imagem legível para conferência</small></span></li>
            <li><x-icon name="check-circle" /><span><strong>Selfie</strong><small>Qualidade adequada para análise</small></span></li>
            <li><x-icon name="check-circle" /><span><strong>Destino e responsável</strong><small>Convite válido e vigente</small></span></li>
        </ul>
    </section>

    <section aria-labelledby="detail-personal-{{ $record['id'] }}">
        <h4 id="detail-personal-{{ $record['id'] }}">Dados preenchidos</h4>
        <dl class="pre-registration-detail__data">
            <div><dt>Nome completo</dt><dd>{{ $record['name'] }}</dd></div>
            <div><dt>Documento</dt><dd>{{ $record['document'] }}</dd></div>
            <div><dt>Data de nascimento</dt><dd>{{ $details['birthDate'] }}</dd></div>
            <div><dt>Telefone</dt><dd>{{ $details['phone'] }}</dd></div>
            <div><dt>E-mail</dt><dd>{{ $details['email'] }}</dd></div>
            <div><dt>Endereço informado</dt><dd>{{ $details['address'] }}</dd></div>
            <div><dt>Destino</dt><dd>{{ $record['destination'] }}</dd></div>
            <div><dt>Responsável</dt><dd>{{ $record['responsible'] }}</dd></div>
            <div><dt>Período</dt><dd>{{ $record['period'] }}</dd></div>
            <div><dt>Veículo</dt><dd>{{ $record['vehicle'] }}</dd></div>
            <div><dt>Enviado em</dt><dd>{{ $record['submittedAt'] }}</dd></div>
            <div><dt>Documento enviado</dt><dd>{{ $details['documentStatus'] }}</dd></div>
            <div><dt>Selfie</dt><dd>{{ $details['selfieStatus'] }}</dd></div>
        </dl>
    </section>

    @if ($editing)
        <section aria-labelledby="detail-edit-{{ $record['id'] }}">
            <h4 id="detail-edit-{{ $record['id'] }}">Corrigir antes de aprovar</h4>
            <x-ui.alert variant="warning" title="Edição auditada">
                A versão enviada pelo solicitante será preservada. Informe a justificativa da correção.
            </x-ui.alert>
            <div class="registration-fields">
                <x-ui.field id="edit-name-{{ $record['id'] }}" label="Nome completo" wire:model="editName" :error="$errors->first('editName')" required />
                <x-ui.field id="edit-phone-{{ $record['id'] }}" label="Telefone" wire:model="editPhone" :error="$errors->first('editPhone')" required />
                <x-ui.field id="edit-email-{{ $record['id'] }}" type="email" label="E-mail" wire:model="editEmail" :error="$errors->first('editEmail')" required />
                <x-ui.field id="edit-birth-date-{{ $record['id'] }}" label="Data de nascimento" wire:model="editBirthDate" placeholder="DD/MM/AAAA" :error="$errors->first('editBirthDate')" required />
                <x-ui.field id="edit-address-{{ $record['id'] }}" label="Endereço informado" wire:model="editAddress" :error="$errors->first('editAddress')" required />
                <x-ui.field id="edit-destination-{{ $record['id'] }}" label="Destino" wire:model="editDestination" :error="$errors->first('editDestination')" required />
                <x-ui.field id="edit-responsible-{{ $record['id'] }}" label="Responsável" wire:model="editResponsible" :error="$errors->first('editResponsible')" required />
                <x-ui.field id="edit-period-{{ $record['id'] }}" label="Período" wire:model="editPeriod" :error="$errors->first('editPeriod')" required />
                <x-ui.field id="edit-vehicle-{{ $record['id'] }}" label="Veículo" wire:model="editVehicle" :error="$errors->first('editVehicle')" required />
                <x-ui.field id="edit-reason-{{ $record['id'] }}" label="Justificativa da correção" wire:model="editReason" help="Obrigatória para auditoria." :error="$errors->first('editReason')" required />
            </div>
            <div class="pre-registration-review-actions">
                <x-ui.button variant="secondary" wire:click="cancelEdit">Cancelar edição</x-ui.button>
                <x-ui.button variant="success" wire:click="saveEdit({{ $record['id'] }})">Salvar correção</x-ui.button>
            </div>
        </section>
    @endif

    <section aria-labelledby="detail-history-{{ $record['id'] }}">
        <h4 id="detail-history-{{ $record['id'] }}">Histórico</h4>
        <ul class="pre-registration-history">
            <li><span></span><div><strong>Pré-cadastro enviado</strong><small>{{ $record['submittedAt'] }} · Fluxo público</small></div></li>
            <li><span></span><div><strong>Disponível para análise</strong><small>Fila da Portaria Principal · versão 1</small></div></li>
            @foreach ($auditEntries as $entry)
                <li><span></span><div><strong>Dados corrigidos por {{ $entry['operator'] }}</strong><small>{{ $entry['at'] }} · {{ $entry['reason'] }}</small><small>{{ $entry['changes'] }}</small></div></li>
            @endforeach
        </ul>
    </section>

    <x-ui.alert variant="info" title="Aprovação não garante entrada">
        Aprovar este pré-cadastro apenas prepara a solicitação para a Validação de Entrada.
    </x-ui.alert>
</div>

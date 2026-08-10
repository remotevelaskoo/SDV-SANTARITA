@props(['record'])

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

    <section aria-labelledby="detail-data-{{ $record['id'] }}">
        <h4 id="detail-data-{{ $record['id'] }}">Detalhes</h4>
        <dl class="pre-registration-detail__data">
            <div><dt>Destino</dt><dd>{{ $record['destination'] }}</dd></div>
            <div><dt>Responsável</dt><dd>{{ $record['responsible'] }}</dd></div>
            <div><dt>Período</dt><dd>{{ $record['period'] }}</dd></div>
            <div><dt>Veículo</dt><dd>{{ $record['vehicle'] }}</dd></div>
            <div><dt>Enviado em</dt><dd>{{ $record['submittedAt'] }}</dd></div>
        </dl>
    </section>

    <section aria-labelledby="detail-history-{{ $record['id'] }}">
        <h4 id="detail-history-{{ $record['id'] }}">Histórico</h4>
        <ul class="pre-registration-history">
            <li><span></span><div><strong>Pré-cadastro enviado</strong><small>{{ $record['submittedAt'] }} · Fluxo público</small></div></li>
            <li><span></span><div><strong>Disponível para análise</strong><small>Fila da Portaria Principal · versão 1</small></div></li>
        </ul>
    </section>

    <x-ui.alert variant="info" title="Aprovação não garante entrada">
        Aprovar este pré-cadastro apenas prepara a solicitação para a Validação de Entrada.
    </x-ui.alert>
</div>

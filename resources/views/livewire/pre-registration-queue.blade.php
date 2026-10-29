<div class="pre-registration-queue">
    <section class="pre-registration-queue__summary" aria-label="Resumo dos pré-cadastros">
        @php
            $statusCounts = [
                'aguardando' => $records->where('status', 'aguardando')->count(),
                'aprovado' => $records->where('status', 'aprovado')->count(),
                'rejeitado' => $records->where('status', 'rejeitado')->count(),
                'correcao' => $records->where('status', 'correcao')->count(),
            ];
        @endphp
        <article><span>Aguardando análise</span><strong>{{ $statusCounts['aguardando'] }}</strong><small>Mais antigas primeiro</small></article>
        <article><span>Aprovados</span><strong>{{ $statusCounts['aprovado'] }}</strong><small>Ainda sujeitos à validação</small></article>
        <article><span>Correção solicitada</span><strong>{{ $statusCounts['correcao'] }}</strong><small>Aguardando novo envio</small></article>
        <article><span>Rejeitados</span><strong>{{ $statusCounts['rejeitado'] }}</strong><small>Registros preservados</small></article>
    </section>

    <section class="pre-registration-queue__workspace" aria-labelledby="pre-registration-list-title">
        <header>
            <div>
                <h2 id="pre-registration-list-title">Solicitações</h2>
                <p>Abra a análise para conferir imagens, dados e leitura automática antes de decidir.</p>
            </div>
            <x-ui.button variant="secondary" href="{{ route('pre-registration.public') }}" target="_blank">
                Abrir formulário público
            </x-ui.button>
        </header>

        <div class="pre-registration-status-tabs" role="group" aria-label="Filtrar por situação">
            <button type="button" wire:click="setStatusFilter('aguardando')" @class(['is-active' => $statusFilter === 'aguardando'])>Aguardando <span>{{ $statusCounts['aguardando'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('aprovado')" @class(['is-active' => $statusFilter === 'aprovado'])>Aprovados <span>{{ $statusCounts['aprovado'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('correcao')" @class(['is-active' => $statusFilter === 'correcao'])>Correção <span>{{ $statusCounts['correcao'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('rejeitado')" @class(['is-active' => $statusFilter === 'rejeitado'])>Rejeitados <span>{{ $statusCounts['rejeitado'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('todos')" @class(['is-active' => $statusFilter === 'todos'])>Todos <span>{{ $records->count() }}</span></button>
        </div>

        <div class="pre-registration-filters">
            <label class="pre-registration-search">
                <span class="sr-only">Buscar pré-cadastros</span>
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar nome, protocolo, placa ou destino">
            </label>
            <x-ui.select id="pre-registration-type-filter" label="Tipo de acesso" disabled>
                <option>Todos os tipos</option>
            </x-ui.select>
            <x-ui.select id="pre-registration-period-filter" label="Período" disabled>
                <option>Últimos 30 dias</option>
            </x-ui.select>
        </div>

        <x-ui.responsive-table
            label="Lista de pré-cadastros"
            :state="$filteredRecords->count() ? 'ready' : 'empty'"
            empty-title="Nenhum pré-cadastro encontrado"
            empty-description="Altere a busca ou selecione outro filtro de situação."
        >
            <x-slot:table>
                <thead>
                    <tr>
                        <th>Pessoa</th>
                        <th>Tipo</th>
                        <th>Envio</th>
                        <th>Veículo</th>
                        <th>Protocolo</th>
                        <th>Situação</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filteredRecords as $record)
                        <tr wire:key="row-{{ $record->id }}">
                            <td><strong>{{ $record->name }}</strong><small>{{ $record->maskedDocument() }} · {{ $record->destination_label }}</small></td>
                            <td>{{ ucfirst($record->access_type) }}</td>
                            <td>{{ $record->submitted_at->format('d/m/Y \à\s H:i') }}</td>
                            <td>{{ $record->vehicleLabel() }}</td>
                            <td class="numeric">{{ $record->protocol }}</td>
                            <td>
                                <x-ui.badge :variant="match ($record->status) { 'aprovado' => 'success', 'rejeitado' => 'danger', 'correcao' => 'warning', default => 'info' }">
                                    {{ match ($record->status) { 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado', 'correcao' => 'Correção', default => 'Aguardando' } }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <x-ui.button variant="secondary" size="sm" href="{{ route('pre-registrations.review', $record->id) }}">Analisar</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-slot:table>

            <x-slot:cards>
                <ul class="pre-registration-mobile-list">
                    @foreach ($filteredRecords as $record)
                        <li wire:key="card-{{ $record->id }}">
                            <header><span class="pre-registration-person-avatar">{{ $record->initials() }}</span><div><strong>{{ $record->name }}</strong><small>{{ ucfirst($record->access_type) }} · {{ $record->destination_label }}</small></div></header>
                            <dl><div><dt>Enviado</dt><dd>{{ $record->submitted_at->format('d/m/Y \à\s H:i') }}</dd></div><div><dt>Protocolo</dt><dd>{{ $record->protocol }}</dd></div><div><dt>Veículo</dt><dd>{{ $record->vehicleLabel() }}</dd></div></dl>
                            <footer>
                                <x-ui.badge :variant="match ($record->status) { 'aprovado' => 'success', 'rejeitado' => 'danger', 'correcao' => 'warning', default => 'info' }">
                                    {{ match ($record->status) { 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado', 'correcao' => 'Correção', default => 'Aguardando' } }}
                                </x-ui.badge>
                                <x-ui.button variant="secondary" size="sm" href="{{ route('pre-registrations.review', $record->id) }}">Analisar</x-ui.button>
                            </footer>
                        </li>
                    @endforeach
                </ul>
            </x-slot:cards>
        </x-ui.responsive-table>

        <footer class="pre-registration-queue__footer"><span>Exibindo {{ $filteredRecords->count() }} de {{ $records->count() }} solicitações</span><small>Ordenação: mais antigas primeiro</small></footer>
    </section>
</div>

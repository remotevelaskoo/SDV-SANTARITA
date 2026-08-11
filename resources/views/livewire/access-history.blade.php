<div class="access-history">
    @if ($mode === 'list')
        @php
            $resultVariant = fn ($result) => match ($result) { 'liberado' => 'success', 'negado' => 'danger', default => 'warning' };
            $resultLabel = fn ($result) => match ($result) { 'liberado' => 'Liberado', 'negado' => 'Negado', default => 'Pendente' };
        @endphp

        <section class="access-history-summary-grid" aria-label="Resumo do período filtrado">
            <article><span>Registros no filtro</span><strong>{{ count($filteredEntries) }}</strong><small>De {{ $entryCounts['total'] }} no total</small></article>
            <article><span>Liberados</span><strong>{{ $entryCounts['liberado'] }}</strong><small>Entradas e saídas autorizadas</small></article>
            <article><span>Negados</span><strong>{{ $entryCounts['negado'] }}</strong><small>Requerem atenção se recorrentes</small></article>
            <article><span>Pendentes</span><strong>{{ $entryCounts['pendente'] }}</strong><small>Aguardando conferência</small></article>
        </section>

        <section class="access-history-list-card" aria-labelledby="access-history-title">
            <header>
                <div><h2 id="access-history-title">Entradas e saídas</h2><p>Consulte o histórico de tentativas de acesso registradas pela portaria.</p></div>
            </header>

            <div class="access-history-filters">
                <label class="access-history-search">
                    <span class="sr-only">Buscar registros</span>
                    <x-icon name="search" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar pessoa, documento, imóvel, placa ou protocolo">
                </label>
                <x-ui.select id="access-history-type-filter" label="Tipo" wire:model.live="typeFilter">
                    <option value="todos">Entradas e saídas</option>
                    <option value="entrada">Somente entradas</option>
                    <option value="saida">Somente saídas</option>
                </x-ui.select>
                <x-ui.select id="access-history-result-filter" label="Resultado" wire:model.live="resultFilter">
                    <option value="todos">Todos os resultados</option>
                    <option value="liberado">Liberado</option>
                    <option value="negado">Negado</option>
                    <option value="pendente">Pendente</option>
                </x-ui.select>
                <x-ui.select id="access-history-point-filter" label="Ponto de acesso" wire:model.live="pointFilter">
                    <option value="todos">Todos os pontos</option>
                    @foreach ($points as $point)
                        <option value="{{ $point }}">{{ $point }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.responsive-table
                label="Histórico de entradas e saídas"
                :state="count($filteredEntries) ? 'ready' : 'empty'"
                empty-title="Nenhum registro encontrado"
                empty-description="Revise a busca ou os filtros selecionados."
            >
                <x-slot:table>
                    <thead><tr><th>Data e hora</th><th>Pessoa</th><th>Vínculo</th><th>Imóvel</th><th>Ponto de acesso</th><th>Tipo</th><th>Resultado</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($filteredEntries as $entry)
                            <tr>
                                <td class="numeric">{{ $entry['datetime'] }}</td>
                                <td><strong>{{ $entry['name'] }}</strong><small>{{ $entry['document'] }}</small></td>
                                <td>{{ $entry['relation'] }}</td>
                                <td>{{ $entry['property'] }}</td>
                                <td>{{ $entry['point'] }}@if ($entry['plate'])<small>🚙 {{ $entry['plate'] }}</small>@endif</td>
                                <td><span class="access-history-type access-history-type--{{ $entry['type'] }}"><x-icon :name="$entry['type'] === 'entrada' ? 'arrow-down-left' : 'arrow-up-right'" />{{ $entry['type'] === 'entrada' ? 'Entrada' : 'Saída' }}</span></td>
                                <td><x-ui.badge :variant="$resultVariant($entry['result'])">{{ $resultLabel($entry['result']) }}</x-ui.badge></td>
                                <td><x-ui.button variant="secondary" size="sm" wire:click="openEntry('{{ $entry['id'] }}')">Detalhes</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="ui-mobile-records">
                        @foreach ($filteredEntries as $entry)
                            <li>
                                <div>
                                    <strong>{{ $entry['name'] }}</strong>
                                    <small>{{ $entry['relation'] }} · {{ $entry['property'] }} · {{ $entry['point'] }}</small>
                                </div>
                                <time>{{ $entry['datetime'] }}</time>
                                <x-ui.badge :variant="$resultVariant($entry['result'])">{{ $resultLabel($entry['result']) }}</x-ui.badge>
                                <x-ui.button variant="ghost" size="sm" wire:click="openEntry('{{ $entry['id'] }}')">Detalhes</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>

            <footer class="access-history-list-footer"><span>Exibindo {{ count($filteredEntries) }} de {{ $entryCounts['total'] }} registros</span></footer>
        </section>
    @elseif ($mode === 'detail' && $selectedEntry)
        @php
            $resultVariant = fn ($result) => match ($result) { 'liberado' => 'success', 'negado' => 'danger', default => 'warning' };
            $resultLabel = fn ($result) => match ($result) { 'liberado' => 'Liberado', 'negado' => 'Negado', default => 'Pendente' };
        @endphp

        <nav class="access-history-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="backToList">Entradas e saídas</button><x-icon name="chevron-right" /><span aria-current="page">{{ $selectedEntry['protocol'] }}</span></nav>

        <x-ui.alert variant="info" title="Registro histórico">Este registro reflete o momento da tentativa de acesso e não pode ser editado por usuários operacionais (RN-048).</x-ui.alert>

        <section class="access-history-detail-hero">
            <div><span>Registro de acesso</span><h2>{{ $selectedEntry['name'] }}</h2><p>{{ $selectedEntry['relation'] }} · {{ $selectedEntry['property'] }} · {{ $selectedEntry['datetime'] }}</p></div>
            <div class="access-history-detail-hero__actions">
                <span class="access-history-type access-history-type--{{ $selectedEntry['type'] }}"><x-icon :name="$selectedEntry['type'] === 'entrada' ? 'arrow-down-left' : 'arrow-up-right'" />{{ $selectedEntry['type'] === 'entrada' ? 'Entrada' : 'Saída' }}</span>
                <x-ui.badge :variant="$resultVariant($selectedEntry['result'])">{{ $resultLabel($selectedEntry['result']) }}</x-ui.badge>
            </div>
        </section>

        @if ($selectedEntry['result'] === 'negado' && $selectedEntry['reason'])
            <x-ui.alert variant="danger" title="Motivo da negação">{{ $selectedEntry['reason'] }}</x-ui.alert>
        @elseif ($selectedEntry['result'] === 'pendente')
            <x-ui.alert variant="warning" title="Aguardando conferência">Este atendimento ainda não foi concluído pela portaria.</x-ui.alert>
        @endif

        <section class="access-history-detail-grid">
            <article class="access-history-section-card">
                <header><div><h3>Dados do registro</h3><p>Conteúdo mínimo do log conforme RN-047.</p></div></header>
                <dl class="access-history-detail-data">
                    <div><dt>Protocolo</dt><dd>{{ $selectedEntry['protocol'] }}</dd></div>
                    <div><dt>Documento</dt><dd>{{ $selectedEntry['document'] }}</dd></div>
                    <div><dt>Imóvel</dt><dd>{{ $selectedEntry['property'] }}</dd></div>
                    <div><dt>Ponto de acesso</dt><dd>{{ $selectedEntry['point'] }}</dd></div>
                    @if ($selectedEntry['plate'])
                        <div><dt>Placa</dt><dd>{{ $selectedEntry['plate'] }}</dd></div>
                    @endif
                    <div><dt>Operador</dt><dd>{{ $selectedEntry['operator'] }}</dd></div>
                </dl>
            </article>

            <article class="access-history-section-card">
                <header><div><h3>Observações</h3><p>Registradas no momento do atendimento.</p></div></header>
                @if ($selectedEntry['notes'])
                    <p class="access-history-notes">{{ $selectedEntry['notes'] }}</p>
                @else
                    <x-ui.empty-state title="Sem observações" description="Nenhuma observação foi registrada para este atendimento." />
                @endif
            </article>
        </section>
    @endif
</div>

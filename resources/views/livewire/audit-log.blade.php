<div class="access-history audit-log">
    <x-ui.alert variant="info" title="Histórico protegido">Os registros desta página não podem ser editados ou apagados. Dados sensíveis são ocultados automaticamente.</x-ui.alert>

    <section class="access-history-list-card" aria-labelledby="audit-log-title">
        <header>
            <div><h2 id="audit-log-title">Operações registradas</h2><p>Consulte quem fez cada ação, quando, onde e o que foi alterado.</p></div>
            <div class="ui-action-group">
                @if ($canExport)
                    <x-ui.button variant="secondary" wire:click="exportCsv"><x-icon name="download" /> Exportar CSV</x-ui.button>
                @endif
                <x-ui.button variant="ghost" wire:click="clearFilters">Limpar filtros</x-ui.button>
            </div>
        </header>

        <div class="access-history-filters">
            <label class="access-history-search">
                <span class="sr-only">Buscar registros</span>
                <x-icon name="search" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar usuário, entidade, identificador ou correlação">
            </label>
            <x-ui.field id="audit-date-from" label="De" type="date" wire:model.live="dateFrom" />
            <x-ui.field id="audit-date-to" label="Até" type="date" wire:model.live="dateTo" />
            <x-ui.select id="audit-module" label="Módulo" wire:model.live="moduleFilter">
                <option value="todos">Todos</option>
                @foreach ($modules as $module)<option value="{{ $module }}">{{ ucfirst($module) }}</option>@endforeach
            </x-ui.select>
            <x-ui.select id="audit-action" label="Operação" wire:model.live="actionFilter">
                <option value="todos">Todas</option>
                @foreach ($actions as $action)<option value="{{ $action }}">{{ str_replace('_', ' ', ucfirst($action)) }}</option>@endforeach
            </x-ui.select>
            <x-ui.select id="audit-result" label="Resultado" wire:model.live="resultFilter">
                <option value="todos">Todos</option>
                <option value="sucesso">Sucesso</option>
                <option value="negado">Negado</option>
                <option value="falha">Falha</option>
                <option value="pendente">Pendente</option>
            </x-ui.select>
            <x-ui.select id="audit-actor" label="Usuário" wire:model.live="actorFilter">
                <option value="todos">Todos</option>
                @foreach ($actors as $actor)<option value="{{ $actor->id }}">{{ $actor->name }}</option>@endforeach
            </x-ui.select>
        </div>

        <x-ui.responsive-table
            label="Registros de auditoria"
            :state="$events->count() ? 'ready' : 'empty'"
            empty-title="Nenhum registro encontrado"
            empty-description="Revise o período ou os filtros selecionados."
        >
            <x-slot:table>
                <thead><tr><th>Data e hora</th><th>Usuário</th><th>Operação</th><th>Entidade</th><th>Origem</th><th>Resultado</th><th>Ações</th></tr></thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr wire:key="audit-{{ $event->id }}">
                            <td class="numeric">{{ $event->occurred_at->format('d/m/Y H:i:s') }}</td>
                            <td><strong>{{ $event->actor_name }}</strong><small>{{ $event->actor_profile ?: ucfirst($event->actor_type) }}</small></td>
                            <td><strong>{{ str_replace('_', ' ', ucfirst($event->action)) }}</strong><small>{{ ucfirst($event->module) }}</small></td>
                            <td>{{ $event->entity_type }}<small>{{ $event->entity_id ?: 'Sem identificador' }}</small></td>
                            <td>{{ ucfirst($event->origin) }}</td>
                            <td><x-ui.badge :variant="$event->result === 'sucesso' ? 'success' : ($event->result === 'negado' ? 'danger' : 'warning')">{{ ucfirst($event->result) }}</x-ui.badge></td>
                            <td><x-ui.button variant="secondary" size="sm" wire:click="openEvent('{{ $event->id }}')">Detalhes</x-ui.button></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-slot:table>
            <x-slot:cards>
                <ul class="ui-mobile-records">
                    @foreach ($events as $event)
                        <li wire:key="audit-card-{{ $event->id }}">
                            <div><strong>{{ $event->actor_name }}</strong><small>{{ ucfirst($event->module) }} · {{ str_replace('_', ' ', $event->action) }}</small></div>
                            <time>{{ $event->occurred_at->format('d/m/Y H:i') }}</time>
                            <x-ui.badge :variant="$event->result === 'sucesso' ? 'success' : 'warning'">{{ ucfirst($event->result) }}</x-ui.badge>
                            <x-ui.button variant="ghost" size="sm" wire:click="openEvent('{{ $event->id }}')">Detalhes</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </x-slot:cards>
        </x-ui.responsive-table>

        <footer class="access-history-list-footer">
            <span>Exibindo {{ $events->firstItem() ?? 0 }}–{{ $events->lastItem() ?? 0 }} de {{ $events->total() }} registros</span>
            {{ $events->links() }}
        </footer>
    </section>

    @if ($selectedEvent)
        <section class="access-history-list-card" aria-labelledby="audit-detail-title">
            <header>
                <div><h2 id="audit-detail-title">Detalhes da auditoria</h2><p>Registro {{ $selectedEvent->id }}</p></div>
                <x-ui.button variant="secondary" wire:click="closeEvent">Fechar detalhes</x-ui.button>
            </header>
            <dl class="access-history-detail-data">
                <div><dt>Data e hora</dt><dd>{{ $selectedEvent->occurred_at->format('d/m/Y H:i:s') }}</dd></div>
                <div><dt>Usuário</dt><dd>{{ $selectedEvent->actor_name }} · {{ $selectedEvent->actor_profile ?: $selectedEvent->actor_type }}</dd></div>
                <div><dt>Operação</dt><dd>{{ str_replace('_', ' ', ucfirst($selectedEvent->action)) }}</dd></div>
                <div><dt>Módulo</dt><dd>{{ ucfirst($selectedEvent->module) }}</dd></div>
                <div><dt>Entidade</dt><dd>{{ $selectedEvent->entity_type }} · {{ $selectedEvent->entity_id ?: '—' }}</dd></div>
                <div><dt>Resultado</dt><dd>{{ ucfirst($selectedEvent->result) }}</dd></div>
                <div><dt>Origem</dt><dd>{{ ucfirst($selectedEvent->origin) }} · {{ $selectedEvent->context?->ip_address ?: 'IP não disponível' }}</dd></div>
                <div><dt>Correlação</dt><dd>{{ $selectedEvent->correlation_id }}</dd></div>
                @if ($selectedEvent->justification)<div><dt>Justificativa</dt><dd>{{ $selectedEvent->justification }}</dd></div>@endif
            </dl>

            <h3>Campos alterados</h3>
            @forelse ($selectedEvent->changes as $change)
                <article class="access-history-section-card">
                    <strong>{{ $change->field_name }}</strong>
                    <p>Antes: {{ is_array($change->old_value) ? json_encode($change->old_value, JSON_UNESCAPED_UNICODE) : ($change->old_value ?? '—') }}</p>
                    <p>Depois: {{ is_array($change->new_value) ? json_encode($change->new_value, JSON_UNESCAPED_UNICODE) : ($change->new_value ?? '—') }}</p>
                    @if ($change->is_masked)<small>Dado protegido automaticamente</small>@endif
                </article>
            @empty
                <x-ui.empty-state title="Sem alteração de campos" description="Este evento registra uma ação de consulta ou exportação." />
            @endforelse
        </section>
    @endif
</div>

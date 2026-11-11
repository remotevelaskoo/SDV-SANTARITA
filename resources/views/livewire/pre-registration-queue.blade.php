<div class="pre-registration-queue sdv-one-pre-registration-queue">
    <section class="sdv-one-page-intro sdv-one-page-intro--queue" aria-labelledby="sdv-one-queue-heading">
        <div class="sdv-one-page-intro__copy">
            <span class="sdv-one-page-intro__eyebrow"><x-icon name="clipboard" /> Solicitações de acesso</span>
            <h2 id="sdv-one-queue-heading">Pré-cadastros</h2>
            <p>Organize a fila, consulte os registros e encaminhe cada solicitação para análise.</p>
        </div>
        <div class="sdv-one-page-intro__aside">
            <span>Foco no atendimento</span>
            <strong>Conferência humana</strong>
            <small>Solicitações não liberam entrada automaticamente.</small>
        </div>
    </section>
    <section class="pre-registration-queue__summary" aria-label="Resumo dos pré-cadastros">
        @php
            $statusCounts = [
                'aguardando' => $records->where('status', 'aguardando')->count(),
                'aprovado' => $records->where('status', 'aprovado')->count(),
                'rejeitado' => $records->where('status', 'rejeitado')->count(),
                'correcao' => $records->where('status', 'correcao')->count(),
            ];
        @endphp
        <article class="sdv-one-status-card sdv-one-status-card--waiting"><span class="sdv-one-status-card__icon" aria-hidden="true"><x-icon name="clock" /></span><span>Aguardando análise</span><strong>{{ $statusCounts['aguardando'] }}</strong><small>Mais antigas primeiro</small></article>
        <article class="sdv-one-status-card sdv-one-status-card--approved"><span class="sdv-one-status-card__icon" aria-hidden="true"><x-icon name="check-circle" /></span><span>Aprovados</span><strong>{{ $statusCounts['aprovado'] }}</strong><small>Ainda sujeitos à validação</small></article>
        <article class="sdv-one-status-card sdv-one-status-card--correction"><span class="sdv-one-status-card__icon" aria-hidden="true"><x-icon name="alert" /></span><span>Correção solicitada</span><strong>{{ $statusCounts['correcao'] }}</strong><small>Aguardando novo envio</small></article>
        <article class="sdv-one-status-card sdv-one-status-card--rejected"><span class="sdv-one-status-card__icon" aria-hidden="true"><x-icon name="x" /></span><span>Rejeitados</span><strong>{{ $statusCounts['rejeitado'] }}</strong><small>Registros preservados</small></article>
    </section>

    @if ($falhasPortal !== [])
        <section class="pre-registration-queue__portal-failures" aria-labelledby="portal-failures-title">
            <x-ui.alert variant="warning" :title="count($falhasPortal) === 1 ? '1 envio do portal precisa de conferência' : count($falhasPortal).' envios do portal precisam de conferência'">
                <p id="portal-failures-title">O sistema tentou importar estes envios várias vezes e parou para não travar a fila. Confira o motivo e, depois de corrigir a causa, peça uma nova tentativa. Nada foi aprovado ou liberado.</p>
                <ul>
                    @foreach ($falhasPortal as $falha)
                        <li wire:key="falha-{{ $falha['id'] }}">
                            <strong>{{ $falha['protocolo'] ?? 'Protocolo não identificado' }}</strong>
                            · {{ $falha['motivo'] }}@if ($falha['campos'] !== []) ({{ implode(', ', $falha['campos']) }})@endif
                            · {{ $falha['tentativas'] }} {{ $falha['tentativas'] === 1 ? 'tentativa' : 'tentativas' }}
                            @if ($falha['situacao'] === 'reabertura_solicitada')
                                · <em>nova tentativa na próxima sincronização</em>
                            @elseif ($podeReabrir)
                                <x-ui.button variant="secondary" size="sm" wire:click="tentarNovamente('{{ $falha['id'] }}')">Tentar novamente</x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.alert>
        </section>
    @endif

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
            <button type="button" wire:click="setStatusFilter('aguardando')" aria-pressed="{{ $statusFilter === 'aguardando' ? 'true' : 'false' }}" @class(['is-active' => $statusFilter === 'aguardando'])>Aguardando <span>{{ $statusCounts['aguardando'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('aprovado')" aria-pressed="{{ $statusFilter === 'aprovado' ? 'true' : 'false' }}" @class(['is-active' => $statusFilter === 'aprovado'])>Aprovados <span>{{ $statusCounts['aprovado'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('correcao')" aria-pressed="{{ $statusFilter === 'correcao' ? 'true' : 'false' }}" @class(['is-active' => $statusFilter === 'correcao'])>Correção <span>{{ $statusCounts['correcao'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('rejeitado')" aria-pressed="{{ $statusFilter === 'rejeitado' ? 'true' : 'false' }}" @class(['is-active' => $statusFilter === 'rejeitado'])>Rejeitados <span>{{ $statusCounts['rejeitado'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('todos')" aria-pressed="{{ $statusFilter === 'todos' ? 'true' : 'false' }}" @class(['is-active' => $statusFilter === 'todos'])>Todos <span>{{ $records->count() }}</span></button>
        </div>

        <div class="pre-registration-filters">
            <p class="sdv-one-filter-hint"><x-icon name="info" /> Pesquise por nome, protocolo, placa ou destino. Os filtros de tipo e período ainda não estão disponíveis.</p>
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
                            <td>
                                <div class="pre-registration-person">
                                    @if ($fotos[$record->id]['url'])
                                        <img class="pre-registration-photo" src="{{ $fotos[$record->id]['url'] }}" alt="Foto de {{ $record->name }}" loading="lazy" referrerpolicy="no-referrer">
                                    @else
                                        <span class="pre-registration-person-avatar" title="{{ $fotos[$record->id]['tem'] ? 'Foto protegida pelo seu perfil' : 'Sem foto' }}">{{ $record->initials() }}</span>
                                    @endif
                                    <div>
                                        <strong>{{ $record->name }}</strong>
                                        <small>{{ $record->maskedDocument() }} · {{ $record->destination_label }}</small>
                                        @if (! $fotos[$record->id]['tem'])
                                            @if ($podeTirarFoto && $record->status !== 'rejeitado')
                                                <a class="pre-registration-photo-missing" href="{{ route('pre-registrations.review', $record->id) }}#tirar-foto"><x-icon name="alert" /> Sem foto · tirar agora</a>
                                            @else
                                                <span class="pre-registration-photo-missing"><x-icon name="alert" /> Sem foto</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </td>
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
                            <header>
                                @if ($fotos[$record->id]['url'])
                                    <img class="pre-registration-photo" src="{{ $fotos[$record->id]['url'] }}" alt="Foto de {{ $record->name }}" loading="lazy" referrerpolicy="no-referrer">
                                @else
                                    <span class="pre-registration-person-avatar">{{ $record->initials() }}</span>
                                @endif
                                <div>
                                    <strong>{{ $record->name }}</strong>
                                    <small>{{ ucfirst($record->access_type) }} · {{ $record->destination_label }}</small>
                                    @if (! $fotos[$record->id]['tem'])
                                        @if ($podeTirarFoto && $record->status !== 'rejeitado')
                                            <a class="pre-registration-photo-missing" href="{{ route('pre-registrations.review', $record->id) }}#tirar-foto"><x-icon name="alert" /> Sem foto · tirar agora</a>
                                        @else
                                            <span class="pre-registration-photo-missing"><x-icon name="alert" /> Sem foto</span>
                                        @endif
                                    @endif
                                </div>
                            </header>
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

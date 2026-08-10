<div class="pre-registration-queue">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    <section class="pre-registration-queue__summary" aria-label="Resumo dos pré-cadastros">
        @php
            $statusCounts = [
                'aguardando' => collect($records)->where('status', 'aguardando')->count(),
                'aprovado' => collect($records)->where('status', 'aprovado')->count(),
                'rejeitado' => collect($records)->where('status', 'rejeitado')->count(),
                'correcao' => collect($records)->where('status', 'correcao')->count(),
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
                <p>Analise os dados antes de tomar uma decisão.</p>
            </div>
            <x-ui.button variant="secondary" href="{{ route('pre-registration.public') }}" target="_blank">
                Abrir formulário público
            </x-ui.button>
        </header>

        <div class="pre-registration-status-tabs" role="group" aria-label="Filtrar por situação">
            <button type="button" wire:click="setStatusFilter('aguardando')" @class(['is-active' => $statusFilter === 'aguardando'])>Aguardando <span>{{ $statusCounts['aguardando'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('aprovado')" @class(['is-active' => $statusFilter === 'aprovado'])>Aprovados <span>{{ $statusCounts['aprovado'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('rejeitado')" @class(['is-active' => $statusFilter === 'rejeitado'])>Rejeitados <span>{{ $statusCounts['rejeitado'] }}</span></button>
            <button type="button" wire:click="setStatusFilter('todos')" @class(['is-active' => $statusFilter === 'todos'])>Todos <span>{{ count($records) }}</span></button>
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
            :state="count($filteredRecords) ? 'ready' : 'empty'"
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
                        <tr>
                            <td><strong>{{ $record['name'] }}</strong><small>{{ $record['document'] }} · {{ $record['destination'] }}</small></td>
                            <td>{{ $record['type'] }}</td>
                            <td>{{ $record['submittedAt'] }}</td>
                            <td>{{ $record['vehicle'] }}</td>
                            <td class="numeric">{{ $record['protocol'] }}</td>
                            <td>
                                <x-ui.badge :variant="match ($record['status']) { 'aprovado' => 'success', 'rejeitado' => 'danger', 'correcao' => 'warning', default => 'info' }">
                                    {{ match ($record['status']) { 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado', 'correcao' => 'Correção', default => 'Aguardando' } }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <x-ui.drawer id="pre-registration-{{ $record['id'] }}" title="Analisar pré-cadastro" description="{{ $record['protocol'] }}" trigger-label="Analisar">
                                    <x-pre-registration-detail :record="$record" />

                                    <x-slot:footer>
                                        @if ($record['status'] === 'aguardando')
                                            <div class="pre-registration-review-actions">
                                                <x-ui.select id="correction-item-{{ $record['id'] }}" label="Pedir correção de" wire:model="correctionItems">
                                                    <option value="dados_pessoais">Dados pessoais</option>
                                                    <option value="documento">Documento</option>
                                                    <option value="selfie">Selfie</option>
                                                    <option value="veiculo">Veículo</option>
                                                </x-ui.select>
                                                <x-ui.button variant="warning" wire:click="requestCorrection({{ $record['id'] }})">Solicitar correção</x-ui.button>
                                                <x-ui.select id="rejection-reason-{{ $record['id'] }}" label="Motivo da rejeição" wire:model="rejectionReason">
                                                    <option value="documento_incompleto">Documento incompleto</option>
                                                    <option value="dados_divergentes">Dados divergentes</option>
                                                    <option value="periodo_invalido">Período inválido</option>
                                                    <option value="solicitacao_nao_confirmada">Solicitação não confirmada</option>
                                                </x-ui.select>
                                                <x-ui.button variant="danger" wire:click="reject({{ $record['id'] }})">Rejeitar</x-ui.button>
                                                <x-ui.button variant="success" wire:click="approve({{ $record['id'] }})">Aprovar pré-cadastro</x-ui.button>
                                            </div>
                                        @else
                                            <x-ui.alert variant="info" title="Decisão já registrada">
                                                Consulte o histórico. Uma nova decisão exigirá um fluxo autorizado.
                                            </x-ui.alert>
                                        @endif
                                    </x-slot:footer>
                                </x-ui.drawer>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-slot:table>

            <x-slot:cards>
                <ul class="pre-registration-mobile-list">
                    @foreach ($filteredRecords as $record)
                        <li>
                            <header><span class="pre-registration-person-avatar">{{ $record['initials'] }}</span><div><strong>{{ $record['name'] }}</strong><small>{{ $record['type'] }} · {{ $record['destination'] }}</small></div></header>
                            <dl><div><dt>Enviado</dt><dd>{{ $record['submittedAt'] }}</dd></div><div><dt>Protocolo</dt><dd>{{ $record['protocol'] }}</dd></div><div><dt>Veículo</dt><dd>{{ $record['vehicle'] }}</dd></div></dl>
                            <footer>
                                <x-ui.badge :variant="match ($record['status']) { 'aprovado' => 'success', 'rejeitado' => 'danger', 'correcao' => 'warning', default => 'info' }">
                                    {{ match ($record['status']) { 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado', 'correcao' => 'Correção', default => 'Aguardando' } }}
                                </x-ui.badge>
                                <x-ui.drawer id="pre-registration-mobile-{{ $record['id'] }}" title="Analisar pré-cadastro" description="{{ $record['protocol'] }}" trigger-label="Analisar">
                                    <x-pre-registration-detail :record="$record" />
                                    <x-slot:footer>
                                        @if ($record['status'] === 'aguardando')
                                            <div class="pre-registration-review-actions">
                                                <x-ui.select id="mobile-correction-item-{{ $record['id'] }}" label="Pedir correção de" wire:model="correctionItems">
                                                    <option value="dados_pessoais">Dados pessoais</option>
                                                    <option value="documento">Documento</option>
                                                    <option value="selfie">Selfie</option>
                                                    <option value="veiculo">Veículo</option>
                                                </x-ui.select>
                                                <x-ui.button variant="warning" wire:click="requestCorrection({{ $record['id'] }})">Solicitar correção</x-ui.button>
                                                <x-ui.select id="mobile-rejection-reason-{{ $record['id'] }}" label="Motivo da rejeição" wire:model="rejectionReason">
                                                    <option value="documento_incompleto">Documento incompleto</option>
                                                    <option value="dados_divergentes">Dados divergentes</option>
                                                    <option value="periodo_invalido">Período inválido</option>
                                                    <option value="solicitacao_nao_confirmada">Solicitação não confirmada</option>
                                                </x-ui.select>
                                                <x-ui.button variant="danger" wire:click="reject({{ $record['id'] }})">Rejeitar</x-ui.button>
                                                <x-ui.button variant="success" wire:click="approve({{ $record['id'] }})">Aprovar pré-cadastro</x-ui.button>
                                            </div>
                                        @endif
                                    </x-slot:footer>
                                </x-ui.drawer>
                            </footer>
                        </li>
                    @endforeach
                </ul>
            </x-slot:cards>
        </x-ui.responsive-table>

        <footer class="pre-registration-queue__footer"><span>Exibindo {{ count($filteredRecords) }} de {{ count($records) }} solicitações</span><small>Ordenação: mais antigas primeiro</small></footer>
    </section>
</div>

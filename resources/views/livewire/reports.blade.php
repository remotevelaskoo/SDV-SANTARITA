<div class="reports-page">
    <section class="reports-toolbar" aria-labelledby="reports-title">
        <div>
            <h2 id="reports-title">Relatórios operacionais</h2>
            <p>Os totais e a exportação usam exatamente o mesmo período, filtros e escopo de permissão.</p>
        </div>
        <x-ui.button variant="secondary" wire:click="exportCsv">
            <x-icon name="download" /> Exportar CSV
        </x-ui.button>
    </section>

    <x-ui.alert variant="info" title="Escopo da consulta">
        @if ($canViewConsolidated)
            Você está consultando dados consolidados da implantação Santa Rita.
        @else
            Seu perfil permite consultar somente operações registradas por você.
        @endif
        Dados sensíveis não são incluídos na exportação desta versão.
        @unless ($canViewPayments) Valor e forma de pagamento não aparecem para o seu perfil, na tela nem na exportação. @endunless
    </x-ui.alert>

    @if (session('ticket-corrigido'))
        <x-ui.toast variant="success" title="Ticket corrigido" :dismissible="false">{{ session('ticket-corrigido') }}</x-ui.toast>
    @endif

    <section class="reports-filters" aria-label="Filtros do relatório">
        <x-ui.select id="reports-type" label="Relatório" wire:model.live="reportType">
            <option value="acessos">Acessos</option>
            <option value="caixa">Caixa</option>
        </x-ui.select>

        <label><span>Data inicial</span><input type="date" wire:model.live="dateFrom" max="{{ $dateTo }}"></label>
        <label><span>Data final</span><input type="date" wire:model.live="dateTo" min="{{ $dateFrom }}"></label>
        <label class="reports-search"><span>Busca</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Nome, protocolo, placa ou descrição"></label>

        @if ($canViewConsolidated)
            <x-ui.select id="reports-operator" label="Operador" wire:model.live="operatorFilter">
                <option value="todos">Todos os operadores</option>
                @foreach ($operators as $operator)
                    <option value="{{ $operator->id }}">{{ $operator->name }}</option>
                @endforeach
            </x-ui.select>
        @endif

        @if ($reportType === 'acessos')
            <x-ui.select id="reports-result" label="Resultado" wire:model.live="resultFilter">
                <option value="todos">Todos</option><option value="liberado">Liberado</option><option value="negado">Negado</option><option value="pendente">Pendente</option>
            </x-ui.select>
            <x-ui.select id="reports-access-type" label="Direção" wire:model.live="accessTypeFilter">
                <option value="todos">Entradas e saídas</option><option value="entrada">Entrada</option><option value="saida">Saída</option>
            </x-ui.select>
            <x-ui.select id="reports-point" label="Ponto de acesso" wire:model.live="pointFilter">
                <option value="todos">Todos os pontos</option>
                @foreach ($points as $point)<option value="{{ $point }}">{{ $point }}</option>@endforeach
            </x-ui.select>
            <x-ui.select id="reports-property" label="Imóvel" wire:model.live="propertyFilter">
                <option value="todos">Todos os imóveis</option>
                @foreach ($properties as $property)<option value="{{ $property->id }}">{{ $property->label() }}</option>@endforeach
            </x-ui.select>
            <x-ui.select id="reports-payment-status" label="Pagamento" wire:model.live="paymentStatusFilter">
                <option value="todos">Todas as situações</option>
                <option value="pago">Contribui/Pago</option>
                <option value="sem_pagamento">Não contribui</option>
                <option value="isento">Isento</option>
                <option value="sem_registro">Sem registro</option>
            </x-ui.select>
            <label><span>Número do ticket</span><input type="search" wire:model.live.debounce.300ms="ticketFilter" maxlength="30" placeholder="Exatamente como no ticket"></label>
        @else
            <x-ui.select id="reports-movement-type" label="Movimentação" wire:model.live="movementTypeFilter">
                <option value="todos">Todas</option><option value="entrada">Entrada</option><option value="saida">Saída</option><option value="estorno">Estorno</option>
            </x-ui.select>
            <x-ui.select id="reports-payment-method" label="Forma" wire:model.live="paymentMethodFilter">
                <option value="todos">Todas</option><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="cartao">Cartão</option>
            </x-ui.select>
        @endif

        <button type="button" class="reports-clear" wire:click="clearFilters">Limpar filtros</button>
    </section>

    @if ($reportType === 'acessos')
        <section class="reports-summary" aria-label="Resumo de acessos">
            <article><span>Registros</span><strong>{{ $accessSummary['total'] }}</strong></article>
            <article><span>Liberados</span><strong>{{ $accessSummary['liberado'] }}</strong></article>
            <article><span>Negados</span><strong>{{ $accessSummary['negado'] }}</strong></article>
            <article><span>Pendentes</span><strong>{{ $accessSummary['pendente'] }}</strong></article>
        </section>

        <section class="reports-table-card">
            <header><div><h3>Relatório de acessos</h3><p>Até 500 registros na tela; a exportação processa todo o recorte filtrado.</p></div></header>
            <x-ui.responsive-table label="Relatório de acessos" :state="$accessRows->isEmpty() ? 'empty' : 'ready'" empty-title="Nenhum acesso encontrado" empty-description="Revise o período ou os filtros selecionados.">
                <x-slot:table>
                    <thead><tr><th>Data e hora</th><th>Pessoa</th><th>Ponto e direção</th><th>Resultado</th><th>Pagamento</th>@if ($canViewPayments)<th>Valor e forma</th>@endif<th>Ticket</th><th>Operador</th></tr></thead>
                    <tbody>@foreach ($accessRows as $entry)
                        @php($contribuicao = $entry->contribuicao)
                        <tr wire:key="acesso-{{ $entry->id }}">
                        <td>{{ $entry->occurred_at->format('d/m/Y H:i') }}</td>
                        <td><strong>{{ $entry->pessoa?->nomeExibicao() ?? 'Não identificado' }}</strong><small>{{ $entry->imovel?->label() ?? 'Sem imóvel' }} · {{ $entry->protocol }}</small></td>
                        <td>{{ $entry->ponto_acesso }}<small>{{ ucfirst($entry->tipo) }}</small></td>
                        <td><x-ui.badge :variant="match($entry->resultado) { 'liberado' => 'success', 'negado' => 'danger', default => 'warning' }">{{ ucfirst($entry->resultado) }}</x-ui.badge></td>
                        <td>{{ \App\Models\Contribuicao::rotuloSituacao($contribuicao?->situacao_pagamento) }}</td>
                        @if ($canViewPayments)
                            <td>{{ $contribuicao?->valor !== null ? 'R$ '.number_format((float) $contribuicao->valor, 2, ',', '.') : '—' }}<small>{{ \App\Models\Contribuicao::rotuloForma($contribuicao?->forma_pagamento) }}</small></td>
                        @endif
                        <td>
                            <span class="numeric">{{ $contribuicao?->ticket_numero ?? '—' }}</span>
                            @if ($contribuicao?->ticket_duplicado)<x-ui.badge variant="warning">Repetido</x-ui.badge>@endif
                            @if ($contribuicao && $canCorrectTicket)
                                @if ($correctingContribuicaoId === $contribuicao->id)
                                    <div class="reports-ticket-fix">
                                        <x-ui.field id="novo-ticket-{{ $contribuicao->id }}" label="Novo número" wire:model="novoTicket" maxlength="30" :error="$errors->first('novoTicket')" />
                                        <x-ui.field id="justificativa-ticket-{{ $contribuicao->id }}" label="Justificativa" wire:model="justificativaTicket" maxlength="300" help="Obrigatória; o valor anterior fica preservado." :error="$errors->first('justificativaTicket')" required />
                                        <footer>
                                            <x-ui.button size="sm" variant="secondary" wire:click="cancelTicketCorrection">Cancelar</x-ui.button>
                                            <x-ui.button size="sm" variant="success" wire:click="saveTicketCorrection" wire:loading.attr="disabled">Salvar correção</x-ui.button>
                                        </footer>
                                    </div>
                                @else
                                    <button type="button" class="sensitive-reveal" wire:click="startTicketCorrection('{{ $contribuicao->id }}')">Corrigir</button>
                                @endif
                            @endif
                        </td>
                        <td>{{ $entry->operator?->name ?? 'Sistema' }}</td>
                    </tr>@endforeach</tbody>
                </x-slot:table>
                <x-slot:cards><ul class="ui-mobile-records">@foreach ($accessRows as $entry)<li><div><strong>{{ $entry->pessoa?->nomeExibicao() ?? 'Não identificado' }}</strong><small>{{ $entry->ponto_acesso }} · {{ ucfirst($entry->tipo) }} · {{ \App\Models\Contribuicao::rotuloSituacao($entry->contribuicao?->situacao_pagamento) }}@if ($entry->contribuicao?->ticket_numero) · Ticket {{ $entry->contribuicao->ticket_numero }}@endif</small></div><time>{{ $entry->occurred_at->format('d/m/Y H:i') }}</time><x-ui.badge :variant="match($entry->resultado) { 'liberado' => 'success', 'negado' => 'danger', default => 'warning' }">{{ ucfirst($entry->resultado) }}</x-ui.badge></li>@endforeach</ul></x-slot:cards>
            </x-ui.responsive-table>
        </section>
    @else
        <section class="reports-summary" aria-label="Resumo do caixa">
            <article><span>Movimentações</span><strong>{{ $cashSummary['movimentos'] }}</strong></article>
            <article><span>Entradas</span><strong>R$ {{ number_format($cashSummary['entradas'], 2, ',', '.') }}</strong></article>
            <article><span>Saídas e estornos</span><strong>R$ {{ number_format($cashSummary['saidas'], 2, ',', '.') }}</strong></article>
            <article><span>Saldo movimentado</span><strong>R$ {{ number_format($cashSummary['total'], 2, ',', '.') }}</strong></article>
        </section>

        <section class="reports-table-card">
            <header><div><h3>Relatório de caixa</h3><p>Movimentações conciliadas com os lançamentos do caixa.</p></div></header>
            <x-ui.responsive-table label="Relatório de caixa" :state="$cashRows->isEmpty() ? 'empty' : 'ready'" empty-title="Nenhuma movimentação encontrada" empty-description="Revise o período ou os filtros selecionados.">
                <x-slot:table>
                    <thead><tr><th>Data e hora</th><th>Caixa</th><th>Descrição</th><th>Tipo</th><th>Forma</th><th>Valor</th><th>Operador</th></tr></thead>
                    <tbody>@foreach ($cashRows as $movement)<tr>
                        <td>{{ $movement->occurred_at->format('d/m/Y H:i') }}</td><td>{{ $movement->caixaTurno?->terminal ?? '—' }}</td>
                        <td>{{ $movement->description }}</td><td>{{ ucfirst($movement->type) }}</td><td>{{ ucfirst($movement->method) }}</td>
                        <td>R$ {{ number_format((float) $movement->amount, 2, ',', '.') }}</td><td>{{ $movement->operator?->name ?? 'Sistema' }}</td>
                    </tr>@endforeach</tbody>
                </x-slot:table>
                <x-slot:cards><ul class="ui-mobile-records">@foreach ($cashRows as $movement)<li><div><strong>{{ $movement->description }}</strong><small>{{ $movement->caixaTurno?->terminal ?? '—' }} · {{ ucfirst($movement->method) }}</small></div><time>{{ $movement->occurred_at->format('d/m/Y H:i') }}</time><strong>R$ {{ number_format((float) $movement->amount, 2, ',', '.') }}</strong></li>@endforeach</ul></x-slot:cards>
            </x-ui.responsive-table>
        </section>
    @endif
</div>

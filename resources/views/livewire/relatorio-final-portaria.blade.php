<div class="relatorio-final">
    <section class="relatorio-final__toolbar reports-toolbar no-print" aria-labelledby="relatorio-final-titulo">
        <div>
            <h2 id="relatorio-final-titulo">Relatório final</h2>
            <p>Escolha o período e imprima ou salve em PDF ao encerrar o turno.</p>
        </div>
        <div class="relatorio-final__acoes">
            <x-ui.button variant="secondary" href="{{ route('portaria') }}"><x-icon name="chevron-left" /> Modo Portaria</x-ui.button>
            <x-ui.button variant="primary" type="button" onclick="window.print()"><x-icon name="download" /> Imprimir / salvar PDF</x-ui.button>
        </div>
    </section>

    <section class="reports-filters no-print" aria-label="Período do relatório">
        <label><span>Data inicial</span><input type="date" wire:model.live="dateFrom" max="{{ $dateTo }}"></label>
        <label><span>Data final</span><input type="date" wire:model.live="dateTo" min="{{ $dateFrom }}"></label>
        @if ($canViewConsolidated)
            <x-ui.select id="relatorio-final-operador" label="Operador" wire:model.live="operatorFilter">
                <option value="todos">Todos os operadores</option>
                @foreach ($operators as $operator)
                    <option value="{{ $operator->id }}">{{ $operator->name }}</option>
                @endforeach
            </x-ui.select>
        @endif
        <button type="button" class="reports-clear" wire:click="hoje">Hoje</button>
    </section>

    <header class="relatorio-final__cabecalho">
        <strong>SDV One · Relatório final da portaria</strong>
        <span>Período: {{ $periodoRotulo }}</span>
        <span>Emitido em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}</span>
    </header>

    @unless ($canViewPayments)
        <x-ui.alert variant="info" title="Valores ocultos">
            Seu perfil não opera nem consulta caixa: valores, formas de pagamento e lançamentos não aparecem neste relatório.
        </x-ui.alert>
    @elseif (! $canViewConsolidated)
        <x-ui.alert variant="info" title="Escopo dos valores">
            Os valores e tickets mostrados são os registrados por você. As entradas e saídas incluem toda a portaria.
        </x-ui.alert>
    @endunless

    <section class="relatorio-final__resumo" aria-label="Resumo do período">
        <article><small>Entradas</small><strong>{{ $resumo['entradas'] }}</strong></article>
        <article><small>Saídas</small><strong>{{ $resumo['saidas'] }}</strong></article>
        <article><small>Pessoas identificadas</small><strong>{{ $resumo['pessoas'] }}</strong></article>
        <article><small>Contribuíram</small><strong>{{ $resumo['pagos'] }}</strong></article>
        <article><small>Não contribuíram</small><strong>{{ $resumo['naoContribui'] }}</strong></article>
        <article><small>Isentos</small><strong>{{ $resumo['isentos'] }}</strong></article>
        @if ($canViewPayments)
            <article class="relatorio-final__total"><small>Total de arrecadação</small><strong>R$ {{ number_format($financeiro['totalArrecadado'], 2, ',', '.') }}</strong></article>
        @endif
    </section>

    @if ($canViewPayments)
        <x-ui.card title="Valores recolhidos" description="Contribuições pagas no atendimento e lançamentos manuais do caixa">
            <table class="relatorio-final__tabela relatorio-final__tabela--totais">
                <tbody>
                    <tr><th scope="row">Contribuições pagas ({{ $financeiro['quantidade'] }} · {{ $financeiro['tickets'] }} {{ $financeiro['tickets'] === 1 ? 'ticket' : 'tickets' }})</th><td class="numeric">R$ {{ number_format($financeiro['contribuicoes'], 2, ',', '.') }}</td></tr>
                    @foreach ($financeiro['porForma'] as $forma => $valor)
                        <tr class="relatorio-final__subitem"><th scope="row">{{ $forma }}</th><td class="numeric">R$ {{ number_format($valor, 2, ',', '.') }}</td></tr>
                    @endforeach
                    <tr><th scope="row">Outras entradas do caixa</th><td class="numeric">R$ {{ number_format($financeiro['outrasEntradas'], 2, ',', '.') }}</td></tr>
                    <tr><th scope="row">Estornos</th><td class="numeric">− R$ {{ number_format($financeiro['estornos'], 2, ',', '.') }}</td></tr>
                    <tr class="relatorio-final__linha-total"><th scope="row">Total de arrecadação</th><td class="numeric">R$ {{ number_format($financeiro['totalArrecadado'], 2, ',', '.') }}</td></tr>
                    <tr class="relatorio-final__subitem"><th scope="row">Saídas do caixa (sangria, despesas — fora do total)</th><td class="numeric">R$ {{ number_format($financeiro['saidas'], 2, ',', '.') }}</td></tr>
                </tbody>
            </table>
        </x-ui.card>

        <x-ui.card title="Tickets por pessoa que contribuiu" description="Números de etiqueta/ticket registrados em cada contribuição paga">
            @if ($contribuintes->isEmpty())
                <p class="relatorio-final__vazio">Nenhuma contribuição paga no período.</p>
            @else
                <div class="relatorio-final__rolagem">
                    <table class="relatorio-final__tabela">
                        <thead>
                            <tr><th>Pessoa</th><th>Imóvel</th><th>Tickets</th><th>Forma</th><th class="numeric">Qtd.</th><th class="numeric">Valor</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($contribuintes as $contribuinte)
                                <tr>
                                    <td><strong>{{ $contribuinte['pessoa'] }}</strong></td>
                                    <td>{{ $contribuinte['imovel'] }}</td>
                                    <td class="relatorio-final__tickets">{{ $contribuinte['tickets'] ? implode(', ', $contribuinte['tickets']) : '—' }}</td>
                                    <td>{{ $contribuinte['formas'] }}</td>
                                    <td class="numeric">{{ $contribuinte['quantidade'] }}</td>
                                    <td class="numeric">R$ {{ number_format($contribuinte['total'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="4">Total das contribuições</th><td class="numeric">{{ $financeiro['quantidade'] }}</td><td class="numeric">R$ {{ number_format($financeiro['contribuicoes'], 2, ',', '.') }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card title="Entradas e saídas" description="Todas as pessoas que entraram e saíram no período, em ordem de horário">
        @if ($acessos->isEmpty())
            <p class="relatorio-final__vazio">Nenhuma entrada ou saída registrada no período.</p>
        @else
            <div class="relatorio-final__rolagem">
                <table class="relatorio-final__tabela">
                    <thead>
                        <tr>
                            <th>Data e hora</th><th>Movimento</th><th>Pessoa</th><th>Imóvel</th><th>Placa</th><th>Resultado</th><th>Contribuição</th><th>Ticket</th>
                            @if ($canViewPayments)<th class="numeric">Valor</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($acessos as $acesso)
                            @php($contribuicao = $acesso->contribuicao)
                            <tr>
                                <td class="numeric">{{ $acesso->occurred_at->format('d/m H:i') }}</td>
                                <td>{{ $acesso->tipo === 'entrada' ? 'Entrada' : 'Saída' }}</td>
                                <td><strong>{{ $acesso->pessoa?->nomeExibicao() ?? 'Não identificado' }}</strong></td>
                                <td>{{ $acesso->imovel?->label() ?? '—' }}</td>
                                <td>{{ $acesso->veiculo?->plate_display ?? '—' }}</td>
                                <td>{{ ucfirst($acesso->resultado) }}</td>
                                <td>{{ $contribuicao ? \App\Models\Contribuicao::rotuloSituacao($contribuicao->situacao_pagamento) : '—' }}</td>
                                <td class="relatorio-final__tickets">{{ $contribuicao?->ticket_numero ?? '—' }}</td>
                                @if ($canViewPayments)
                                    <td class="numeric">{{ $contribuicao?->valor !== null ? 'R$ '.number_format((float) $contribuicao->valor, 2, ',', '.') : '—' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    @if ($canViewPayments && $movimentacoes->isNotEmpty())
        <x-ui.card title="Lançamentos manuais do caixa" description="Movimentações registradas na tela de Caixa no período">
            <div class="relatorio-final__rolagem">
                <table class="relatorio-final__tabela">
                    <thead><tr><th>Data e hora</th><th>Tipo</th><th>Forma</th><th>Descrição</th><th>Operador</th><th class="numeric">Valor</th></tr></thead>
                    <tbody>
                        @foreach ($movimentacoes as $movimentacao)
                            <tr>
                                <td class="numeric">{{ $movimentacao->occurred_at->format('d/m H:i') }}</td>
                                <td>{{ ucfirst($movimentacao->type) }}</td>
                                <td>{{ \App\Models\Contribuicao::rotuloForma($movimentacao->method) }}</td>
                                <td>{{ $movimentacao->description }}</td>
                                <td>{{ $movimentacao->operator?->name ?? 'Sistema' }}</td>
                                <td class="numeric">R$ {{ number_format((float) $movimentacao->amount, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    <footer class="relatorio-final__assinatura" aria-label="Conferência">
        <span>Responsável pelo turno: ______________________________</span>
        <span>Conferido por: ______________________________</span>
    </footer>
</div>

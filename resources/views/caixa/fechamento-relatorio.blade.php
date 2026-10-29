{{-- Corpo do fechamento de caixa (AJ-008), compartilhado pela tela e pela impressão. --}}
@php($dinheiro = fn ($valor) => 'R$ '.number_format((float) $valor, 2, ',', '.'))
@php($comDiferenca = $turno->closing_status === 'diferenca')
{{-- Fechamentos anteriores ao AJ-008 não gravaram os totais por categoria. --}}
@php($legado = $turno->contribuicoes_total === null)

<section class="fechamento-caixa" aria-labelledby="fechamento-titulo">
    <header class="fechamento-caixa__cabecalho">
        <div>
            <p class="fechamento-caixa__rotulo">Fechamento de caixa</p>
            <h2 id="fechamento-titulo">{{ $turno->numero_fechamento ?? $turno->id }}</h2>
            <p>{{ $turno->implantacao?->nome ?? 'Implantação Santa Rita' }} · {{ $turno->posto?->nome ?? 'Sem posto' }} · {{ $turno->terminal }}</p>
        </div>
        <span @class(['fechamento-caixa__situacao', 'is-diferenca' => $comDiferenca])>{{ $comDiferenca ? 'Diferença' : 'Conferido' }}</span>
    </header>

    <dl class="fechamento-caixa__dados">
        <div><dt>Operador responsável</dt><dd>{{ $turno->operator?->name ?? 'Sistema' }}</dd></div>
        <div><dt>Abertura</dt><dd>{{ $turno->opened_at->format('d/m/Y H:i') }}</dd></div>
        <div><dt>Encerramento</dt><dd>{{ $turno->closed_at?->format('d/m/Y H:i') }}</dd></div>
        <div><dt>Encerrado por</dt><dd>{{ $turno->closedBy?->name ?? $turno->operator?->name ?? 'Sistema' }}</dd></div>
    </dl>

    <table class="fechamento-caixa__totais">
        <caption>Conciliação do turno</caption>
        <tbody>
            <tr><th scope="row">Saldo inicial</th><td>{{ $dinheiro($turno->opening_balance) }}</td></tr>
            @if ($legado)
                <tr><th scope="row" colspan="2" class="fechamento-caixa__legado">Fechamento anterior ao detalhamento por categoria (AJ-008): contribuições, outras entradas, saídas e estornos não foram gravados separadamente.</th></tr>
            @else
                <tr><th scope="row">+ Contribuições pagas ({{ $quantidades['pagantes'] }})</th><td>{{ $dinheiro($turno->contribuicoes_total) }}</td></tr>
                <tr><th scope="row">+ Outras entradas</th><td>{{ $dinheiro($turno->outras_entradas_total) }}</td></tr>
                <tr><th scope="row">− Saídas</th><td>{{ $dinheiro($turno->saidas_total) }}</td></tr>
                <tr><th scope="row">− Estornos e cancelamentos</th><td>{{ $dinheiro($turno->estornos_total) }}</td></tr>
            @endif
            <tr class="is-total"><th scope="row">= Total esperado</th><td>{{ $dinheiro($turno->expected_amount) }}</td></tr>
            <tr><th scope="row">Total contado</th><td>{{ $dinheiro($turno->informed_amount) }}</td></tr>
            <tr @class(['is-total', 'is-diferenca' => $comDiferenca])><th scope="row">Diferença</th><td>{{ $dinheiro($turno->difference) }}</td></tr>
        </tbody>
    </table>

    @if ($turno->closing_notes)
        <p class="fechamento-caixa__justificativa"><strong>{{ $comDiferenca ? 'Justificativa da diferença' : 'Observação' }}:</strong> {{ $turno->closing_notes }}</p>
    @endif

    <section class="fechamento-caixa__quantidades" aria-label="Resumo operacional">
        <div><strong>{{ $quantidades['pagantes'] }}</strong><span>Pagantes</span></div>
        <div><strong>{{ $quantidades['isentos'] }}</strong><span>Isentos</span></div>
        <div><strong>{{ $quantidades['nao_contribuintes'] }}</strong><span>Não contribuintes</span></div>
        <p>Isentos e não contribuintes aparecem só como quantidade e não entram no valor do caixa.</p>
    </section>

    <h3 class="fechamento-caixa__subtitulo">Pessoas pagantes</h3>
    @if ($pagantes->isEmpty())
        <p class="fechamento-caixa__vazio">Nenhuma contribuição paga neste turno.</p>
    @else
        <div class="fechamento-caixa__tabela">
            <table>
                <thead>
                    <tr><th>Horário</th><th>Pessoa</th><th>Documento</th><th>Protocolo</th><th>Ticket</th><th class="numeric">Valor</th><th>Forma</th><th>Operador</th><th>Ponto</th></tr>
                </thead>
                <tbody>
                    @foreach ($pagantes as $linha)
                        <tr>
                            <td>{{ $linha['horario']?->format('d/m/Y H:i') }}</td>
                            <td>{{ $linha['pessoa'] }}</td>
                            <td class="is-codigo">{{ $linha['documento'] }}</td>
                            <td class="is-codigo">{{ $linha['protocolo'] ?? '—' }}</td>
                            <td>
                                {{ $linha['ticket'] ?? '—' }}
                                @if ($linha['ticket_repetido'])<small class="fechamento-caixa__marca">Ticket repetido</small>@endif
                                @if ($linha['ticket_anterior'] !== null || $linha['ticket_corrigido_em'])<small class="fechamento-caixa__marca">Corrigido em {{ $linha['ticket_corrigido_em']?->format('d/m/Y H:i') }} · anterior: {{ $linha['ticket_anterior'] ?? 'sem ticket' }}</small>@endif
                            </td>
                            <td class="numeric">{{ $dinheiro($linha['valor']) }}</td>
                            <td>{{ $linha['forma'] }}</td>
                            <td>{{ $linha['operador'] }}</td>
                            <td>{{ $linha['ponto'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th colspan="5" scope="row">Total das pessoas pagantes</th><td class="numeric">{{ $dinheiro($pagantes->sum('valor')) }}</td><td colspan="3"></td></tr>
                </tfoot>
            </table>
        </div>
    @endif

    <p class="fechamento-caixa__emissao">Emitido em {{ now()->format('d/m/Y H:i') }}. Correções posteriores de ticket ficam sinalizadas e preservam o valor anterior na auditoria.</p>
</section>

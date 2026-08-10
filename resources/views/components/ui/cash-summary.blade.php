@props([
    'operator',
    'terminal',
    'openedAt',
    'openingBalance',
    'income',
    'expenses',
    'cancellations',
    'expected',
    'informed',
    'difference',
])

<article {{ $attributes->class('ui-cash-summary') }}>
    <header><div><span class="ui-cash-summary__icon"><x-icon name="wallet" /></span><div><strong>Caixa aberto</strong><small>{{ $terminal }} · desde {{ $openedAt }}</small></div></div><x-ui.badge variant="success">Operando</x-ui.badge></header>
    <p>Operador: <strong>{{ $operator }}</strong></p>
    <dl><div><dt>Saldo inicial</dt><dd>{{ $openingBalance }}</dd></div><div><dt>Entradas</dt><dd class="is-positive">{{ $income }}</dd></div><div><dt>Saídas</dt><dd>{{ $expenses }}</dd></div><div><dt>Cancelamentos</dt><dd>{{ $cancellations }}</dd></div><div class="is-total"><dt>Saldo esperado</dt><dd>{{ $expected }}</dd></div><div><dt>Total informado</dt><dd>{{ $informed }}</dd></div><div class="is-difference"><dt>Diferença</dt><dd>{{ $difference }}</dd></div></dl>
    @if (isset($actions))<footer>{{ $actions }}</footer>@endif
</article>

@props([
    'label',
    'value',
    'period',
    'comparison' => null,
    'trend' => 'stable',
    'icon' => 'chart',
    'state' => 'Atualizado',
])

<article {{ $attributes->class('ui-metric') }}>
    <header><span><x-icon :name="$icon" /></span><x-ui.badge>{{ $state }}</x-ui.badge></header>
    <p>{{ $label }}</p>
    <strong>{{ $value }}</strong>
    <footer>
        <span>{{ $period }}</span>
        @if ($comparison)<span @class(['is-up' => $trend === 'up', 'is-down' => $trend === 'down'])>{{ $comparison }}</span>@endif
    </footer>
</article>

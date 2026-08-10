@props([
    'status',
    'equipment',
    'lastAttempt',
    'tone' => 'success',
    'description' => null,
])

<article {{ $attributes->class(['ui-sync-status', "ui-sync-status--{$tone}"]) }}>
    <span class="ui-sync-status__icon"><x-icon :name="$tone === 'danger' ? 'alert' : ($tone === 'warning' ? 'clock' : 'refresh')" /></span>
    <div><header><strong>{{ $status }}</strong><x-ui.badge :variant="$tone">{{ $equipment }}</x-ui.badge></header>@if ($description)<p>{{ $description }}</p>@endif<small>Última tentativa: {{ $lastAttempt }}</small></div>
    @if (isset($action))<div class="ui-sync-status__action">{{ $action }}</div>@endif
</article>

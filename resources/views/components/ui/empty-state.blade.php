@props([
    'title',
    'description',
    'icon' => 'inbox',
])

<div {{ $attributes->class(['ui-empty-state']) }}>
    <span class="ui-empty-state__icon" aria-hidden="true"><x-icon :name="$icon" /></span>
    <strong>{{ $title }}</strong>
    <p>{{ $description }}</p>
    @if (isset($action))
        <div class="ui-empty-state__action">{{ $action }}</div>
    @endif
</div>

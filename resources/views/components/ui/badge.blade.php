@props([
    'variant' => 'neutral',
    'icon' => null,
])

<span {{ $attributes->class(['ui-badge', "ui-badge--{$variant}"]) }}>
    @if ($icon)
        <x-icon :name="$icon" />
    @endif
    <span>{{ $slot }}</span>
</span>

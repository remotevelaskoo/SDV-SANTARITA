@props([
    'variant' => 'info',
    'title' => null,
    'icon' => null,
])

@php
    $alertIcon = $icon ?? match ($variant) {
        'success' => 'check-circle',
        'warning', 'danger' => 'alert',
        default => 'info',
    };
@endphp

<div role="{{ $variant === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class(['ui-alert', "ui-alert--{$variant}"]) }}>
    <x-icon :name="$alertIcon" />
    <div class="ui-alert__content">
        @if ($title)
            <strong>{{ $title }}</strong>
        @endif
        <div class="ui-alert__message">{{ $slot }}</div>
        @if (isset($action))
            <div class="ui-alert__action">{{ $action }}</div>
        @endif
    </div>
</div>

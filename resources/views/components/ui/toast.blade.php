@props([
    'variant' => 'success',
    'title',
    'dismissible' => true,
])

@php
    $toastIcon = match ($variant) {
        'danger', 'warning' => 'alert',
        'info' => 'info',
        default => 'check-circle',
    };
@endphp

<div x-data="{ visible: true }" x-show="visible" x-transition role="{{ $variant === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class(['ui-toast', "ui-toast--{$variant}"]) }}>
    <x-icon :name="$toastIcon" />
    <div><strong>{{ $title }}</strong><p>{{ $slot }}</p>@if (isset($action))<div class="ui-toast__action">{{ $action }}</div>@endif</div>
    @if ($dismissible)<button type="button" aria-label="Fechar aviso" x-on:click="visible = false"><x-icon name="x" /></button>@endif
</div>

@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'loading' => false,
    'loadingLabel' => 'Processando…',
    'disabled' => false,
    'iconOnly' => false,
])

@php
    $classes = [
        'ui-button',
        "ui-button--{$variant}",
        "ui-button--{$size}",
        'ui-button--icon-only' => $iconOnly,
        'is-loading' => $loading,
        'is-disabled' => $disabled,
    ];
@endphp

@if ($href)
    <a
        href="{{ $disabled ? '#' : $href }}"
        @if ($disabled) aria-disabled="true" tabindex="-1" @endif
        {{ $attributes->class($classes) }}
    >
        @if (isset($icon))
            <span class="ui-button__icon">{{ $icon }}</span>
        @endif
        <span class="ui-button__content">{{ $slot }}</span>
    </a>
@else
    <button
        type="{{ $type }}"
        @disabled($disabled || $loading)
        @if ($loading) aria-busy="true" @endif
        {{ $attributes->class($classes) }}
    >
        @if ($loading)
            <span class="ui-spinner" aria-hidden="true"></span>
            <span class="ui-button__content">{{ $loadingLabel }}</span>
        @else
            @if (isset($icon))
                <span class="ui-button__icon">{{ $icon }}</span>
            @endif
            <span class="ui-button__content">{{ $slot }}</span>
        @endif
    </button>
@endif

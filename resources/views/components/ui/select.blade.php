@props([
    'id',
    'label',
    'name' => null,
    'help' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $helpId = "{$id}-help";
    $errorId = "{$id}-error";
    $describedBy = $error ? $errorId : ($help ? $helpId : null);
@endphp

<label class="ui-field" for="{{ $id }}">
    <span class="ui-field__label">
        {{ $label }}
        @if ($required)
            <span class="ui-field__required" aria-hidden="true">*</span>
            <span class="sr-only">(obrigatório)</span>
        @endif
    </span>

    <span class="ui-select-control">
        <select
            id="{{ $id }}"
            name="{{ $name ?? $id }}"
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            @required($required)
            @disabled($disabled)
            {{ $attributes->class(['ui-field__control', 'ui-field__select', 'is-invalid' => $error]) }}
        >
            {{ $slot }}
        </select>
        <x-icon name="chevron-down" />
    </span>

    @if ($error)
        <small id="{{ $errorId }}" class="ui-field__message ui-field__message--error">{{ $error }}</small>
    @elseif ($help)
        <small id="{{ $helpId }}" class="ui-field__message">{{ $help }}</small>
    @endif
</label>

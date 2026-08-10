@props([
    'id',
    'label',
    'name' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'help' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
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

    <input
        id="{{ $id }}"
        name="{{ $name ?? $id }}"
        type="{{ $type }}"
        @if (! is_null($value)) value="{{ $value }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        @required($required)
        @disabled($disabled)
        @readonly($readonly)
        {{ $attributes->class(['ui-field__control', 'is-invalid' => $error]) }}
    >

    @if ($error)
        <small id="{{ $errorId }}" class="ui-field__message ui-field__message--error">{{ $error }}</small>
    @elseif ($help)
        <small id="{{ $helpId }}" class="ui-field__message">{{ $help }}</small>
    @endif
</label>

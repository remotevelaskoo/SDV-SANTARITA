@props([
    'id',
    'label',
    'description' => null,
    'name',
    'value',
    'checked' => false,
    'disabled' => false,
])

<label class="ui-choice" for="{{ $id }}">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="radio"
        value="{{ $value }}"
        @checked($checked)
        @disabled($disabled)
        {{ $attributes->class('ui-choice__input') }}
    >
    <span class="ui-choice__text">
        <strong>{{ $label }}</strong>
        @if ($description)
            <small>{{ $description }}</small>
        @endif
    </span>
</label>

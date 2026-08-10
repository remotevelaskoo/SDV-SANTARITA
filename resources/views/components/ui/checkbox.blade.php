@props([
    'id',
    'label',
    'description' => null,
    'name' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
])

<label class="ui-choice" for="{{ $id }}">
    <input
        id="{{ $id }}"
        name="{{ $name ?? $id }}"
        type="checkbox"
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

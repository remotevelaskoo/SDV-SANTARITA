@props([
    'id',
    'label',
    'description' => null,
    'name' => null,
    'checked' => false,
    'disabled' => false,
])

<label class="ui-switch" for="{{ $id }}">
    <span class="ui-switch__text">
        <strong>{{ $label }}</strong>
        @if ($description)
            <small>{{ $description }}</small>
        @endif
    </span>
    <span class="ui-switch__control">
        <input
            id="{{ $id }}"
            name="{{ $name ?? $id }}"
            type="checkbox"
            role="switch"
            value="1"
            @checked($checked)
            @disabled($disabled)
            {{ $attributes }}
        >
        <span aria-hidden="true"></span>
    </span>
</label>

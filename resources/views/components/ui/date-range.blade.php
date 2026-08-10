@props([
    'id',
    'label' => 'Período',
    'start' => null,
    'end' => null,
    'timezone' => 'Horário de Brasília',
    'allowIndefinite' => false,
])

<fieldset {{ $attributes->class('ui-date-range') }}>
    <legend>{{ $label }}</legend>
    <div class="ui-date-range__fields">
        <label>
            <span>Início</span>
            <span class="ui-date-control"><x-icon name="calendar" /><input id="{{ $id }}-start" type="date" value="{{ $start }}"></span>
        </label>
        <label>
            <span>Término</span>
            <span class="ui-date-control"><x-icon name="calendar" /><input id="{{ $id }}-end" type="date" value="{{ $end }}"></span>
        </label>
    </div>
    <small>{{ $timezone }} · formato exibido conforme o dispositivo</small>
    @if ($allowIndefinite)
        <x-ui.checkbox :id="$id.'-indefinite'" label="Prazo indeterminado" description="O acesso permanecerá válido até suspensão autorizada." />
    @endif
</fieldset>

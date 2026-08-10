@props(['name' => 'access_type', 'selected' => 'resident'])

@php
    $types = [
        ['value' => 'resident', 'label' => 'Morador', 'description' => 'Residente com vínculo ativo', 'icon' => 'building'],
        ['value' => 'tenant', 'label' => 'Inquilino', 'description' => 'Ocupante com contrato vigente', 'icon' => 'key'],
        ['value' => 'provider', 'label' => 'Prestador', 'description' => 'Serviço autorizado', 'icon' => 'wrench'],
        ['value' => 'visitor', 'label' => 'Visitante', 'description' => 'Entrada vinculada a responsável', 'icon' => 'users'],
        ['value' => 'tourist', 'label' => 'Turista', 'description' => 'Hospedagem com período definido', 'icon' => 'package'],
    ];
@endphp

<fieldset {{ $attributes->class('ui-access-types') }}>
    <legend>Tipo de acesso</legend>
    <div>
        @foreach ($types as $type)
            <label>
                <input type="radio" name="{{ $name }}" value="{{ $type['value'] }}" @checked($selected === $type['value'])>
                <span class="ui-access-types__icon"><x-icon :name="$type['icon']" /></span>
                <span><strong>{{ $type['label'] }}</strong><small>{{ $type['description'] }}</small></span>
                <x-icon name="check-circle" class="ui-access-types__check" />
            </label>
        @endforeach
    </div>
</fieldset>

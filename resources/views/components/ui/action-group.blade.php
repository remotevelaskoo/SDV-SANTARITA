@props(['align' => 'end'])

<div {{ $attributes->class(['ui-action-group', "ui-action-group--{$align}"]) }}>
    {{ $slot }}
</div>

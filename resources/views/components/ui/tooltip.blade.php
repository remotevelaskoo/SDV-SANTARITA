@props(['text', 'position' => 'top'])

<span {{ $attributes->class(['ui-tooltip', "ui-tooltip--{$position}"]) }} tabindex="0">
    {{ $slot }}
    <span role="tooltip">{{ $text }}</span>
</span>

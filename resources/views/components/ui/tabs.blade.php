@props(['default'])

<div x-data="{ active: '{{ $default }}' }" {{ $attributes->class('ui-tabs') }}>
    {{ $slot }}
</div>

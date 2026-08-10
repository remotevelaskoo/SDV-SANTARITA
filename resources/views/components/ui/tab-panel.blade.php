@props(['id'])

<section
    id="panel-{{ $id }}"
    role="tabpanel"
    aria-labelledby="tab-{{ $id }}"
    x-show="active === '{{ $id }}'"
    x-cloak
    {{ $attributes->class('ui-tab-panel') }}
>
    {{ $slot }}
</section>

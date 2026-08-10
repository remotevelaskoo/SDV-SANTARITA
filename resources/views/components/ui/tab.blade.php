@props([
    'id',
    'label',
])

<button
    id="tab-{{ $id }}"
    type="button"
    role="tab"
    aria-controls="panel-{{ $id }}"
    x-on:click="active = '{{ $id }}'"
    x-on:keydown.right.prevent="const next = $el.nextElementSibling ?? $el.parentElement.firstElementChild; next.click(); next.focus()"
    x-on:keydown.left.prevent="const previous = $el.previousElementSibling ?? $el.parentElement.lastElementChild; previous.click(); previous.focus()"
    x-on:keydown.home.prevent="const first = $el.parentElement.firstElementChild; first.click(); first.focus()"
    x-on:keydown.end.prevent="const last = $el.parentElement.lastElementChild; last.click(); last.focus()"
    x-bind:aria-selected="active === '{{ $id }}'"
    x-bind:tabindex="active === '{{ $id }}' ? 0 : -1"
    x-bind:class="{ 'is-active': active === '{{ $id }}' }"
    {{ $attributes->class('ui-tab') }}
>{{ $label }}</button>

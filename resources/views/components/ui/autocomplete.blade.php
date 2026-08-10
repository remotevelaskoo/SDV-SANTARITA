@props([
    'id',
    'label',
    'options' => [],
    'placeholder' => 'Digite para buscar',
    'help' => null,
    'required' => false,
])

<div
    x-data="{
        query: '',
        open: false,
        active: -1,
        items: @js($options),
        filtered() { return this.items.filter(item => `${item.label} ${item.description ?? ''}`.toLowerCase().includes(this.query.toLowerCase())) },
        choose(item) { this.query = item.label; this.open = false; this.active = -1 },
        move(step) { const total = this.filtered().length; if (total) this.active = (this.active + step + total) % total },
    }"
    class="ui-autocomplete"
    @click.outside="open = false"
>
    <label for="{{ $id }}" class="ui-field__label">
        {{ $label }} @if ($required)<span class="ui-field__required" aria-hidden="true">*</span>@endif
    </label>
    <div class="ui-autocomplete__control">
        <x-icon name="search" />
        <input
            id="{{ $id }}"
            type="search"
            class="ui-field__control"
            placeholder="{{ $placeholder }}"
            x-model="query"
            x-on:focus="open = true"
            x-on:input="open = true; active = -1"
            x-on:keydown.down.prevent="move(1)"
            x-on:keydown.up.prevent="move(-1)"
            x-on:keydown.enter.prevent="if (active >= 0) choose(filtered()[active])"
            x-on:keydown.escape="open = false"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $id }}-results"
            x-bind:aria-expanded="open"
            @required($required)
        >
    </div>
    @if ($help)<span class="ui-field__message">{{ $help }}</span>@endif
    <div id="{{ $id }}-results" class="ui-autocomplete__results" role="listbox" x-show="open" x-cloak>
        <template x-for="(item, index) in filtered()" :key="item.value">
            <button
                type="button"
                role="option"
                x-bind:aria-selected="active === index"
                x-bind:class="{ 'is-active': active === index }"
                x-on:mouseenter="active = index"
                x-on:click="choose(item)"
            >
                <strong x-text="item.label"></strong>
                <small x-text="item.description"></small>
            </button>
        </template>
        <p x-show="filtered().length === 0">Nenhum resultado encontrado. Revise a busca.</p>
    </div>
</div>

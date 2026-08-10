@props([
    'id',
    'title',
    'description' => null,
    'triggerLabel' => 'Abrir painel',
])

<div x-data class="ui-overlay-trigger">
    <x-ui.button variant="secondary" x-on:click="$refs['{{ $id }}'].showModal()">{{ $triggerLabel }}</x-ui.button>

    <dialog x-ref="{{ $id }}" class="ui-drawer" aria-labelledby="{{ $id }}-title" @click.self="$el.close()">
        <div class="ui-drawer__surface">
            <header>
                <div>
                    <h3 id="{{ $id }}-title">{{ $title }}</h3>
                    @if ($description)
                        <p>{{ $description }}</p>
                    @endif
                </div>
                <form method="dialog">
                    <x-ui.button type="submit" variant="ghost" size="sm" :icon-only="true" aria-label="Fechar painel">
                        <x-slot:icon><x-icon name="x" /></x-slot:icon>
                        <span class="sr-only">Fechar</span>
                    </x-ui.button>
                </form>
            </header>
            <div class="ui-drawer__body">{{ $slot }}</div>
            @if (isset($footer))
                <footer>{{ $footer }}</footer>
            @endif
        </div>
    </dialog>
</div>

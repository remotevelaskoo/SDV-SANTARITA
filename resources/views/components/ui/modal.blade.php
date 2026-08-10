@props([
    'id',
    'title',
    'description' => null,
    'triggerLabel' => 'Abrir confirmação',
    'confirmLabel' => 'Confirmar',
    'confirmVariant' => 'primary',
])

<div x-data class="ui-overlay-trigger">
    <x-ui.button variant="secondary" x-on:click="$refs['{{ $id }}'].showModal()">{{ $triggerLabel }}</x-ui.button>

    <dialog x-ref="{{ $id }}" class="ui-dialog" aria-labelledby="{{ $id }}-title" @click.self="$el.close()">
        <div class="ui-dialog__surface">
            <header>
                <div>
                    <h3 id="{{ $id }}-title">{{ $title }}</h3>
                    @if ($description)
                        <p>{{ $description }}</p>
                    @endif
                </div>
                <form method="dialog">
                    <x-ui.button type="submit" variant="ghost" size="sm" :icon-only="true" aria-label="Fechar janela">
                        <x-slot:icon><x-icon name="x" /></x-slot:icon>
                        <span class="sr-only">Fechar</span>
                    </x-ui.button>
                </form>
            </header>
            <div class="ui-dialog__body">{{ $slot }}</div>
            <footer>
                <form method="dialog"><x-ui.button type="submit" variant="secondary">Cancelar</x-ui.button></form>
                @if (isset($confirm))
                    {{ $confirm }}
                @else
                    <form method="dialog"><x-ui.button type="submit" :variant="$confirmVariant">{{ $confirmLabel }}</x-ui.button></form>
                @endif
            </footer>
        </div>
    </dialog>
</div>

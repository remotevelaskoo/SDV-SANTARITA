@props(['documentLink' => null, 'selfieLink' => null, 'id', 'title' => 'Conferência visual protegida'])

<section
    class="protected-file-review"
    aria-labelledby="protected-files-{{ $id }}"
    x-data="{ previewUrl: null, previewTitle: '' }"
>
    <header>
        <div>
            <h4 id="protected-files-{{ $id }}">{{ $title }}</h4>
            <p>Abra somente durante a conferência de identidade. Cada visualização será registrada na auditoria.</p>
        </div>
        <x-ui.badge variant="warning">Dados sensíveis</x-ui.badge>
    </header>

    <div class="protected-file-review__grid">
        @foreach ([['label' => 'Documento', 'link' => $documentLink], ['label' => 'Selfie', 'link' => $selfieLink]] as $item)
            <article>
                <span class="protected-file-review__icon"><x-icon :name="$item['label'] === 'Documento' ? 'file' : 'users'" /></span>
                <div>
                    <strong>{{ $item['label'] }}</strong>
                    @if ($item['link']?->file?->isAvailable())
                        <small>Disponível para conferência</small>
                    @elseif ($item['link']?->file)
                        <small>Arquivo {{ str_replace('_', ' ', $item['link']->file->status) }}</small>
                    @else
                        <small>Não enviado neste pré-cadastro</small>
                    @endif
                </div>

                @if ($item['link']?->file?->isAvailable())
                    @can('view', $item['link']->file)
                        <button
                            type="button"
                            class="ui-button ui-button--secondary ui-button--sm"
                            x-on:click="previewTitle = @js($item['label']); previewUrl = @js(route('protected-files.show', $item['link']->file)); $nextTick(() => $refs.viewer.showModal())"
                        >
                            <span class="ui-button__content">Abrir {{ strtolower($item['label']) }}</span>
                        </button>
                    @else
                        <small>Seu perfil não permite abrir este arquivo.</small>
                    @endcan
                @endif
            </article>
        @endforeach
    </div>

    <dialog
        x-ref="viewer"
        class="protected-file-viewer"
        aria-labelledby="protected-file-viewer-title-{{ $id }}"
        x-on:close="previewUrl = null; previewTitle = ''"
        @click.self="$el.close()"
    >
        <div class="protected-file-viewer__surface">
            <header>
                <div>
                    <h4 id="protected-file-viewer-title-{{ $id }}" x-text="previewTitle"></h4>
                    <p>Visualização temporária para conferência. Download não é oferecido.</p>
                </div>
                <form method="dialog"><x-ui.button type="submit" variant="ghost" size="sm">Fechar</x-ui.button></form>
            </header>
            <div class="protected-file-viewer__canvas">
                <img x-show="previewUrl" x-bind:src="previewUrl" x-bind:alt="previewTitle" />
            </div>
            <x-ui.alert variant="info" title="Uso restrito">Abrir esta imagem não cria credencial biométrica nem autoriza entrada.</x-ui.alert>
        </div>
    </dialog>
</section>

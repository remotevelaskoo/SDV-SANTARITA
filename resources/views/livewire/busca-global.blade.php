<div class="global-search"
    x-data="{
        aberto: false,
        ativo: -1,
        itens() { return [...$el.querySelectorAll('[data-resultado]')] },
        mover(passo) {
            const lista = this.itens();
            if (!lista.length) return;
            this.aberto = true;
            this.ativo = (this.ativo + passo + lista.length) % lista.length;
            lista[this.ativo].scrollIntoView({ block: 'nearest' });
        },
        abrir() {
            const lista = this.itens();
            const alvo = lista[this.ativo] ?? (lista.length === 1 ? lista[0] : null);
            if (alvo) window.location = alvo.href;
        },
    }"
    x-on:click.outside="aberto = false"
    x-on:keydown.escape="aberto = false; ativo = -1"
>
    <label class="sr-only" for="busca-global">Busca global</label>
    <x-icon name="search" />
    <input
        id="busca-global"
        type="search"
        autocomplete="off"
        placeholder="Buscar pessoa, CPF, placa, imóvel ou protocolo"
        wire:model.live.debounce.300ms="termo"
        x-on:focus="aberto = true"
        x-on:input="aberto = true; ativo = -1"
        x-on:keydown.arrow-down.prevent="mover(1)"
        x-on:keydown.arrow-up.prevent="mover(-1)"
        x-on:keydown.enter.prevent="abrir()"
        role="combobox"
        aria-autocomplete="list"
        aria-controls="busca-global-resultados"
        x-bind:aria-expanded="aberto ? 'true' : 'false'"
        maxlength="80"
    >
    <span class="global-search__carregando" wire:loading wire:target="termo" aria-hidden="true"></span>

    @unless ($this->curto())
        <div id="busca-global-resultados" class="global-search__painel" x-show="aberto" x-cloak role="listbox" aria-label="Resultados da busca global" data-sem-rolagem>
            <div wire:loading.delay wire:target="termo" class="global-search__estado" role="status">Buscando…</div>
            <div wire:loading.remove wire:target="termo">
                @forelse ($this->grupos as $grupo)
                    <section class="global-search__grupo" aria-label="{{ $grupo['titulo'] }}">
                        <header>
                            <span>{{ $grupo['titulo'] }}</span>
                            @if ($grupo['ver_todos'])
                                <a href="{{ $grupo['ver_todos'] }}">{{ $grupo['mais'] ? 'Ver todos' : 'Abrir no módulo' }}</a>
                            @endif
                        </header>
                        <ul>
                            @foreach ($grupo['itens'] as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" data-resultado role="option"
                                       x-bind:class="{ 'is-ativo': itens().indexOf($el) === ativo }"
                                       x-bind:aria-selected="itens().indexOf($el) === ativo ? 'true' : 'false'">
                                        <strong>{!! $this->destacar($item['titulo']) !!}</strong>
                                        @if ($item['detalhe'] !== '')<small>{!! $this->destacar($item['detalhe']) !!}</small>@endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @empty
                    <p class="global-search__estado" role="status">Nada encontrado para "{{ $termo }}". CPF e telefone precisam estar completos.</p>
                @endforelse
            </div>
        </div>
    @endunless
</div>

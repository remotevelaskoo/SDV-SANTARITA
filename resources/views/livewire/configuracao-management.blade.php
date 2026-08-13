<div class="configuracao-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($mode === 'list')
        @foreach ($configuracoesPorCategoria as $categoria => $configuracoes)
            <x-ui.card :title="ucfirst($categoria)" class="configuracao-management-category">
                <ul class="configuracao-management-list">
                    @foreach ($configuracoes as $configuracao)
                        <li>
                            <div class="configuracao-management-list__info">
                                <strong>{{ $configuracao['rotulo'] }}</strong>
                                @if ($configuracao['descricao'])
                                    <small>{{ $configuracao['descricao'] }}</small>
                                @endif
                                <div class="configuracao-management-list__valor">
                                    <span>{{ $configuracao['valorAtual'] ?? '—' }}</span>
                                    <x-ui.badge :variant="$configuracao['isCustomizado'] ? 'success' : 'neutral'">
                                        {{ $configuracao['isCustomizado'] ? 'Customizado' : 'Padrão' }}
                                    </x-ui.badge>
                                </div>
                            </div>
                            <x-ui.button variant="secondary" size="sm" wire:click="editConfiguracao('{{ $configuracao['chave'] }}')">Editar</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endforeach
    @elseif ($mode === 'form' && $editingConfiguracao)
        <x-ui.card title="{{ $editingConfiguracao['rotulo'] }}" description="{{ $editingConfiguracao['descricao'] }}">
            <form wire:submit="salvarConfiguracao" class="configuracao-management-form">
                <x-ui.field
                    id="configuracao-valor"
                    label="Valor"
                    :type="$editingConfiguracao['tipo'] === 'numero' ? 'number' : 'text'"
                    wire:model="valorInput"
                    :placeholder="$editingConfiguracao['valorPadrao'] ?? 'Sem valor padrão'"
                    :error="$errors->first('valorInput')"
                />

                <p class="configuracao-management-form__hint">Deixe em branco para usar o valor padrão.</p>

                <footer>
                    <x-ui.button type="button" variant="secondary" wire:click="backToList">Cancelar</x-ui.button>
                    @if ($editingConfiguracao['isCustomizado'])
                        <x-ui.button type="button" variant="ghost" wire:click="restaurarPadrao">Restaurar padrão</x-ui.button>
                    @endif
                    <x-ui.button type="submit" variant="primary">Salvar</x-ui.button>
                </footer>
            </form>
        </x-ui.card>
    @endif
</div>

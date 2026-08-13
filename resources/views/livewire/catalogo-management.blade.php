<div class="catalogo-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($mode === 'list')
        <x-ui.card title="Motivos de negativa" description="Opções usadas ao negar um acesso na Validação de entrada">
            <x-slot:headerAction>
                <x-ui.button variant="primary" size="sm" wire:click="createItem">Novo motivo</x-ui.button>
            </x-slot:headerAction>

            <x-ui.responsive-table
                label="Lista de motivos"
                :state="count($itensDoCatalogo) ? 'ready' : 'empty'"
                empty-title="Nenhum motivo cadastrado"
                empty-description="Crie o primeiro motivo de negativa."
            >
                <x-slot:table>
                    <thead><tr><th>Código</th><th>Rótulo</th><th>Situação</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($itensDoCatalogo as $item)
                            <tr>
                                <td><code>{{ $item['codigo'] }}</code></td>
                                <td>{{ $item['rotulo'] }}</td>
                                <td>
                                    <x-ui.badge :variant="$item['status'] === 'ativo' ? 'success' : 'neutral'">
                                        {{ $item['statusLabel'] }}
                                    </x-ui.badge>
                                </td>
                                <td class="catalogo-management-actions">
                                    <x-ui.button variant="secondary" size="sm" wire:click="editItem('{{ $item['id'] }}')">Editar</x-ui.button>
                                    @if ($item['status'] === 'ativo')
                                        <x-ui.button variant="ghost" size="sm" wire:click="inativarItem('{{ $item['id'] }}')">Inativar</x-ui.button>
                                    @else
                                        <x-ui.button variant="ghost" size="sm" wire:click="reativarItem('{{ $item['id'] }}')">Reativar</x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="ui-mobile-records">
                        @foreach ($itensDoCatalogo as $item)
                            <li>
                                <div>
                                    <strong>{{ $item['rotulo'] }}</strong>
                                    <small><code>{{ $item['codigo'] }}</code></small>
                                </div>
                                <x-ui.badge :variant="$item['status'] === 'ativo' ? 'success' : 'neutral'">
                                    {{ $item['statusLabel'] }}
                                </x-ui.badge>
                                <div class="catalogo-management-actions">
                                    <x-ui.button variant="ghost" size="sm" wire:click="editItem('{{ $item['id'] }}')">Editar</x-ui.button>
                                    @if ($item['status'] === 'ativo')
                                        <x-ui.button variant="ghost" size="sm" wire:click="inativarItem('{{ $item['id'] }}')">Inativar</x-ui.button>
                                    @else
                                        <x-ui.button variant="ghost" size="sm" wire:click="reativarItem('{{ $item['id'] }}')">Reativar</x-ui.button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>
        </x-ui.card>
    @elseif ($mode === 'form')
        <x-ui.card title="{{ $editingItemId ? 'Editar motivo' : 'Novo motivo' }}" description="Usado no formulário de negativa de acesso">
            <form wire:submit="salvarItem" class="catalogo-management-form">
                <x-ui.field
                    id="catalogo-item-codigo"
                    label="Código interno"
                    wire:model="codigo"
                    placeholder="Ex.: suspeita_fraude"
                    :error="$errors->first('codigo')"
                    :disabled="(bool) $editingItemId"
                    required
                />
                <x-ui.field id="catalogo-item-rotulo" label="Rótulo exibido" wire:model="rotulo" placeholder="Ex.: Suspeita de fraude" :error="$errors->first('rotulo')" required />

                <footer>
                    <x-ui.button type="button" variant="secondary" wire:click="backToList">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Salvar</x-ui.button>
                </footer>
            </form>
        </x-ui.card>
    @endif
</div>

<div class="perfil-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($mode === 'list')
        <section class="perfil-management-summary-grid" aria-label="Resumo de perfis">
            <article><span>Ativos</span><strong>{{ $perfilCounts['ativo'] }}</strong></article>
            <article><span>Inativos</span><strong>{{ $perfilCounts['inativo'] }}</strong></article>
        </section>

        <x-ui.card title="Perfis e permissões" description="Catálogo de perfis desta implantação">
            <x-slot:headerAction>
                <x-ui.button variant="primary" size="sm" wire:click="createPerfil">Novo perfil</x-ui.button>
            </x-slot:headerAction>

            <div class="perfil-management-filters">
                <label class="perfil-management-search">
                    <span class="sr-only">Buscar perfil</span>
                    <x-icon name="search" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome">
                </label>
                <x-ui.select id="perfil-status-filter" label="Situação" wire:model.live="statusFilter">
                    <option value="todos">Todas as situações</option>
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </x-ui.select>
            </div>

            <x-ui.responsive-table
                label="Lista de perfis"
                :state="count($filteredPerfis) ? 'ready' : 'empty'"
                empty-title="Nenhum perfil encontrado"
                empty-description="Revise a busca ou os filtros selecionados."
            >
                <x-slot:table>
                    <thead><tr><th>Nome</th><th>Permissões</th><th>Usuários vinculados</th><th>Situação</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($filteredPerfis as $perfil)
                            <tr>
                                <td><strong>{{ $perfil['nome'] }}</strong></td>
                                <td>{{ $perfil['permissoesCount'] }}</td>
                                <td>{{ $perfil['usuariosCount'] }}</td>
                                <td>
                                    <x-ui.badge :variant="$perfil['status'] === 'ativo' ? 'success' : 'neutral'">
                                        {{ $perfil['statusLabel'] }}
                                    </x-ui.badge>
                                </td>
                                <td><x-ui.button variant="secondary" size="sm" wire:click="openPerfil('{{ $perfil['id'] }}')">Detalhes</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="ui-mobile-records">
                        @foreach ($filteredPerfis as $perfil)
                            <li>
                                <div>
                                    <strong>{{ $perfil['nome'] }}</strong>
                                    <small>{{ $perfil['permissoesCount'] }} permissões · {{ $perfil['usuariosCount'] }} usuários</small>
                                </div>
                                <x-ui.badge :variant="$perfil['status'] === 'ativo' ? 'success' : 'neutral'">
                                    {{ $perfil['statusLabel'] }}
                                </x-ui.badge>
                                <x-ui.button variant="ghost" size="sm" wire:click="openPerfil('{{ $perfil['id'] }}')">Detalhes</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>
        </x-ui.card>
    @elseif ($mode === 'form')
        <x-ui.card title="{{ $editingPerfilId ? 'Editar perfil' : 'Novo perfil' }}" description="Nome e permissões concedidas">
            <form wire:submit="savePerfil" class="perfil-management-form">
                <x-ui.field id="perfil-nome" label="Nome do perfil" wire:model="nome" placeholder="Ex.: Financeiro" :error="$errors->first('nome')" required />

                @error('permissaoIds') <small class="ui-field__message ui-field__message--error">{{ $message }}</small> @enderror

                <div class="perfil-management-matrix">
                    @foreach ($permissoesPorModulo as $modulo => $permissoes)
                        <fieldset class="ui-choice-group">
                            <legend>{{ ucfirst($modulo) }}</legend>
                            @foreach ($permissoes as $permissao)
                                <x-ui.checkbox
                                    :id="'perm-'.$permissao->id"
                                    :label="$permissao->descricao"
                                    :value="$permissao->id"
                                    :checked="in_array($permissao->id, $permissaoIds, true)"
                                    wire:model="permissaoIds"
                                />
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>

                <footer>
                    <x-ui.button type="button" variant="secondary" wire:click="backToList">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Salvar perfil</x-ui.button>
                </footer>
            </form>
        </x-ui.card>
    @elseif ($mode === 'detail' && $selectedPerfil)
        <nav class="perfil-management-breadcrumb" aria-label="Caminho da página">
            <button type="button" wire:click="backToList">Perfis</button>
            <x-icon name="chevron-right" />
            <span aria-current="page">{{ $selectedPerfil['nome'] }}</span>
        </nav>

        <x-ui.card title="{{ $selectedPerfil['nome'] }}" description="{{ $selectedPerfil['permissoesCount'] }} permissões · {{ $selectedPerfil['usuariosCount'] }} usuários vinculados">
            <x-slot:headerAction>
                <x-ui.badge :variant="$selectedPerfil['status'] === 'ativo' ? 'success' : 'neutral'">
                    {{ $selectedPerfil['statusLabel'] }}
                </x-ui.badge>
            </x-slot:headerAction>

            <dl class="perfil-management-detail-data">
                <div><dt>Permissões concedidas</dt><dd>{{ $selectedPerfil['permissoesChaves'] ? implode(', ', $selectedPerfil['permissoesChaves']) : 'Nenhuma' }}</dd></div>
                <div><dt>Usuários vinculados</dt><dd>{{ $selectedPerfil['usuarios'] ? implode(', ', $selectedPerfil['usuarios']) : 'Nenhum' }}</dd></div>
            </dl>

            <x-ui.button variant="secondary" wire:click="editPerfil('{{ $selectedPerfil['id'] }}')">Editar</x-ui.button>

            @if ($selectedPerfil['isCriticalAndLast'])
                <x-ui.alert variant="warning" title="Único concedente de uma permissão crítica">
                    Este perfil não pode ser inativado enquanto for o único que concede uma permissão crítica (administrar usuários ou perfis) a um usuário ativo.
                </x-ui.alert>
            @elseif ($selectedPerfil['status'] === 'inativo')
                <x-ui.button variant="success" wire:click="reactivatePerfil">Reativar</x-ui.button>
            @else
                <form wire:submit="inactivatePerfil" class="perfil-management-reason-form">
                    <x-ui.field id="perfil-inactivate-reason" label="Motivo da inativação" wire:model="inactivateReason" placeholder="Ex.: perfil substituído por outro" :error="$errors->first('inactivateReason')" required />
                    <x-ui.button type="submit" variant="danger">Inativar</x-ui.button>
                </form>
            @endif
        </x-ui.card>
    @endif
</div>

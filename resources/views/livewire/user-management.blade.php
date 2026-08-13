<div class="user-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($mode === 'list')
        <section class="user-management-summary-grid" aria-label="Resumo de usuários">
            <article><span>Convite pendente</span><strong>{{ $userCounts['pendente'] }}</strong></article>
            <article><span>Ativos</span><strong>{{ $userCounts['ativo'] }}</strong></article>
            <article><span>Bloqueados</span><strong>{{ $userCounts['bloqueado'] }}</strong></article>
            <article><span>Inativos</span><strong>{{ $userCounts['inativo'] }}</strong></article>
        </section>

        <x-ui.card title="Usuários" description="Contas operacionais desta implantação">
            <x-slot:headerAction>
                <x-ui.button variant="primary" size="sm" wire:click="createUser">Convidar usuário</x-ui.button>
            </x-slot:headerAction>

            <div class="user-management-filters">
                <label class="user-management-search">
                    <span class="sr-only">Buscar usuário</span>
                    <x-icon name="search" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome, identificação ou e-mail">
                </label>
                <x-ui.select id="user-status-filter" label="Situação" wire:model.live="statusFilter">
                    <option value="todos">Todas as situações</option>
                    <option value="pendente">Convite pendente</option>
                    <option value="ativo">Ativo</option>
                    <option value="bloqueado">Bloqueado</option>
                    <option value="inativo">Inativo</option>
                </x-ui.select>
            </div>

            <x-ui.responsive-table
                label="Lista de usuários"
                :state="count($filteredUsers) ? 'ready' : 'empty'"
                empty-title="Nenhum usuário encontrado"
                empty-description="Revise a busca ou os filtros selecionados."
            >
                <x-slot:table>
                    <thead><tr><th>Nome</th><th>Identificação</th><th>E-mail</th><th>Perfil</th><th>Situação</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($filteredUsers as $user)
                            <tr>
                                <td><strong>{{ $user['name'] }}</strong>@if ($user['isSelf'])<small>Você</small>@endif</td>
                                <td>{{ $user['username'] }}</td>
                                <td>{{ $user['email'] }}</td>
                                <td>{{ $user['perfilAtual'] }}</td>
                                <td>
                                    <x-ui.badge :variant="match($user['status']) { 'ativo' => 'success', 'bloqueado' => 'warning', 'inativo' => 'danger', default => 'neutral' }">
                                        {{ $user['statusLabel'] }}
                                    </x-ui.badge>
                                </td>
                                <td><x-ui.button variant="secondary" size="sm" wire:click="openUser('{{ $user['id'] }}')">Detalhes</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="ui-mobile-records">
                        @foreach ($filteredUsers as $user)
                            <li>
                                <div>
                                    <strong>{{ $user['name'] }}</strong>
                                    <small>{{ $user['username'] }} · {{ $user['perfilAtual'] }}</small>
                                </div>
                                <x-ui.badge :variant="match($user['status']) { 'ativo' => 'success', 'bloqueado' => 'warning', 'inativo' => 'danger', default => 'neutral' }">
                                    {{ $user['statusLabel'] }}
                                </x-ui.badge>
                                <x-ui.button variant="ghost" size="sm" wire:click="openUser('{{ $user['id'] }}')">Detalhes</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>
        </x-ui.card>
    @elseif ($mode === 'form')
        <x-ui.card title="Convidar usuário" description="A pessoa recebe um link por e-mail para definir a própria senha">
            <form wire:submit="saveUser" class="user-management-form">
                <x-ui.field id="user-name" label="Nome" wire:model="name" placeholder="Nome completo" :error="$errors->first('name')" required />
                <x-ui.field id="user-username" label="Identificação" wire:model="username" placeholder="Ex.: joao.silva" :error="$errors->first('username')" required />
                <x-ui.field id="user-email" label="E-mail" type="email" wire:model="email" placeholder="nome@exemplo.com" :error="$errors->first('email')" required />

                <x-ui.select id="user-perfil" label="Perfil inicial" wire:model="perfilId" :error="$errors->first('perfilId')" required>
                    <option value="">Selecione um perfil</option>
                    @foreach ($perfis as $perfil)
                        <option value="{{ $perfil->id }}">{{ $perfil->nome }}</option>
                    @endforeach
                </x-ui.select>

                <footer>
                    <x-ui.button type="button" variant="secondary" wire:click="backToList">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Enviar convite</x-ui.button>
                </footer>
            </form>
        </x-ui.card>
    @elseif ($mode === 'detail' && $selectedUser)
        <nav class="user-management-breadcrumb" aria-label="Caminho da página">
            <button type="button" wire:click="backToList">Usuários</button>
            <x-icon name="chevron-right" />
            <span aria-current="page">{{ $selectedUser['name'] }}</span>
        </nav>

        <x-ui.card title="{{ $selectedUser['name'] }}" description="{{ $selectedUser['username'] }} · {{ $selectedUser['email'] }}">
            <x-slot:headerAction>
                <x-ui.badge :variant="match($selectedUser['status']) { 'ativo' => 'success', 'bloqueado' => 'warning', 'inativo' => 'danger', default => 'neutral' }">
                    {{ $selectedUser['statusLabel'] }}
                </x-ui.badge>
            </x-slot:headerAction>

            <dl class="user-management-detail-data">
                <div><dt>Perfil vigente</dt><dd>{{ $selectedUser['perfilAtual'] }}</dd></div>
                @if ($selectedUser['invitedAt'])
                    <div><dt>Convidado em</dt><dd>{{ $selectedUser['invitedAt'] }}</dd></div>
                @endif
                @if ($selectedUser['statusReason'])
                    <div><dt>Motivo da situação atual</dt><dd>{{ $selectedUser['statusReason'] }}</dd></div>
                @endif
            </dl>

            @if ($selectedUser['isSelf'])
                <x-ui.alert variant="info" title="Esta é a sua própria conta">
                    Não é possível bloquear ou inativar a própria conta por aqui.
                </x-ui.alert>
            @elseif ($selectedUser['isLastAdmin'])
                <x-ui.alert variant="warning" title="Último administrador ativo">
                    Este usuário não pode ser bloqueado nem inativado enquanto for o único com a permissão de administrar usuários.
                </x-ui.alert>
            @else
                @if ($selectedUser['status'] === 'bloqueado')
                    <x-ui.button variant="success" wire:click="unblockUser">Desbloquear</x-ui.button>
                @elseif (in_array($selectedUser['status'], ['ativo', 'pendente'], true))
                    <form wire:submit="blockUser" class="user-management-reason-form">
                        <x-ui.field id="block-reason" label="Motivo do bloqueio" wire:model="blockReason" placeholder="Ex.: acesso suspenso para revisão" :error="$errors->first('blockReason')" required />
                        <x-ui.button type="submit" variant="secondary">Bloquear</x-ui.button>
                    </form>
                @endif

                @if ($selectedUser['status'] !== 'inativo')
                    <form wire:submit="inactivateUser" class="user-management-reason-form">
                        <x-ui.field id="inactivate-reason" label="Motivo da inativação" wire:model="inactivateReason" placeholder="Ex.: desligamento da equipe" :error="$errors->first('inactivateReason')" required />
                        <x-ui.button type="submit" variant="danger">Inativar</x-ui.button>
                    </form>
                @endif
            @endif
        </x-ui.card>
    @endif
</div>

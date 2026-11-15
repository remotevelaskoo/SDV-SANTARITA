<div class="user-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($mode === 'list')
        <section class="user-management-summary-grid" aria-label="Resumo de usuários">
            <article><span>Online agora</span><strong>{{ $userCounts['online'] }}</strong></article>
            <article><span>Ativos</span><strong>{{ $userCounts['ativo'] }}</strong></article>
            <article><span>Com painel remoto</span><strong>{{ $userCounts['painel'] }}</strong></article>
            <article><span>Bloqueados</span><strong>{{ $userCounts['bloqueado'] }}</strong></article>
            <article><span>Inativos</span><strong>{{ $userCounts['inativo'] }}</strong></article>
        </section>

        <x-ui.card title="Usuários" description="Colaboradores desta implantação">
            <x-slot:headerAction>
                <x-ui.button variant="primary" size="sm" wire:click="createUser">Novo usuário</x-ui.button>
            </x-slot:headerAction>

            <div class="user-management-filters">
                <label class="user-management-search">
                    <span class="sr-only">Buscar usuário</span>
                    <x-icon name="search" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome, identificação ou e-mail">
                </label>
                <x-ui.select id="user-status-filter" label="Situação" wire:model.live="statusFilter">
                    <option value="todos">Todas as situações</option>
                    <option value="online">Online agora</option>
                    <option value="ativo">Ativo</option>
                    <option value="pendente">Convite pendente</option>
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
                    <thead><tr><th>Nome</th><th>Identificação</th><th>Perfil</th><th>Agora</th><th>Painel remoto</th><th>Situação</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($filteredUsers as $user)
                            <tr wire:key="usuario-{{ $user['id'] }}">
                                <td><strong>{{ $user['name'] }}</strong>@if ($user['isSelf'])<small>Você</small>@endif</td>
                                <td>{{ $user['username'] }}</td>
                                <td>{{ $user['perfilAtual'] }}</td>
                                <td>
                                    @if ($user['online'])
                                        <span class="user-presence user-presence--online">Online{{ $user['posto'] ? ' · '.$user['posto'] : '' }}</span>
                                    @elseif ($user['posto'])
                                        <span class="user-presence user-presence--posto">No posto {{ $user['posto'] }}</span>
                                    @else
                                        <span class="user-presence">Offline</span>
                                    @endif
                                </td>
                                <td>{{ $user['painel'] ? 'Sim' : '—' }}</td>
                                <td>
                                    <x-ui.badge :variant="match($user['status']) { 'ativo' => 'success', 'bloqueado' => 'warning', 'inativo' => 'danger', default => 'neutral' }">
                                        {{ $user['statusLabel'] }}
                                    </x-ui.badge>
                                </td>
                                <td><x-ui.button variant="secondary" size="sm" wire:click="openUser('{{ $user['id'] }}')">Gerenciar</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="ui-mobile-records">
                        @foreach ($filteredUsers as $user)
                            <li wire:key="usuario-card-{{ $user['id'] }}">
                                <div>
                                    <strong>{{ $user['name'] }}</strong>
                                    <small>{{ $user['username'] }} · {{ $user['perfilAtual'] }} · {{ $user['online'] ? 'Online' : 'Offline' }}{{ $user['posto'] ? ' · '.$user['posto'] : '' }}</small>
                                </div>
                                <x-ui.badge :variant="match($user['status']) { 'ativo' => 'success', 'bloqueado' => 'warning', 'inativo' => 'danger', default => 'neutral' }">
                                    {{ $user['statusLabel'] }}
                                </x-ui.badge>
                                <x-ui.button variant="ghost" size="sm" wire:click="openUser('{{ $user['id'] }}')">Gerenciar</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>
        </x-ui.card>
    @elseif ($mode === 'form')
        <x-ui.card title="Novo usuário" description="O colaborador entra com a senha inicial e escolhe uma senha pessoal no primeiro acesso">
            <form wire:submit="saveUser" class="user-management-form">
                <fieldset class="user-management-group">
                    <legend>Dados do colaborador</legend>
                    <x-ui.field id="user-name" label="Nome" wire:model="name" placeholder="Nome completo" :error="$errors->first('name')" required />
                    <x-ui.field id="user-username" label="Identificação (login)" wire:model="username" placeholder="Ex.: joao.silva" help="Usada para entrar no sistema." :error="$errors->first('username')" required />
                    <x-ui.field id="user-email" label="E-mail" type="email" wire:model="email" placeholder="nome@exemplo.com" help="Também é o login do painel remoto." :error="$errors->first('email')" required />
                    <x-ui.select id="user-perfil" label="Perfil" wire:model="perfilId" :error="$errors->first('perfilId')" required>
                        <option value="">Selecione um perfil</option>
                        @foreach ($perfis as $perfil)
                            <option value="{{ $perfil->id }}">{{ $perfil->nome }}</option>
                        @endforeach
                    </x-ui.select>
                </fieldset>

                <fieldset class="user-management-group">
                    <legend>Senha inicial</legend>
                    <x-ui.field id="user-senha" label="Senha inicial" type="password" wire:model="senhaInicial" autocomplete="new-password" help="Mínimo de 8 caracteres. Entregue ao colaborador; ele troca no primeiro acesso." :error="$errors->first('senhaInicial')" required />
                    <x-ui.field id="user-senha-repetir" label="Repita a senha inicial" type="password" wire:model="senhaInicial_confirmation" autocomplete="new-password" required />
                </fieldset>

                <fieldset class="user-management-group">
                    <legend>Painel de gestão remoto</legend>
                    <x-ui.checkbox id="user-painel" label="Acesso ao painel de gestão remoto" description="Pode ver pela internet a arrecadação, os caixas e quem entrou no dia." wire:model.live="acessoPainel" />
                    @if ($acessoPainel)
                        <x-ui.field id="user-senha-painel" label="Senha do painel remoto" type="password" wire:model="senhaPainel" autocomplete="new-password" help="Mínimo de 10 caracteres. Pode definir depois, no detalhe do usuário." :error="$errors->first('senhaPainel')" />
                        <x-ui.field id="user-senha-painel-repetir" label="Repita a senha do painel" type="password" wire:model="senhaPainel_confirmation" autocomplete="new-password" />
                    @endif
                </fieldset>

                <footer>
                    <x-ui.button type="button" variant="secondary" wire:click="backToList">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Criar usuário</x-ui.button>
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
                <div><dt>Agora</dt><dd>{{ $selectedUser['online'] ? 'Online (último uso às '.$selectedUser['ultimoUso'].')' : 'Offline' }}</dd></div>
                <div><dt>Posto</dt><dd>{{ $selectedUser['posto'] ?? 'Nenhum' }}</dd></div>
                <div><dt>Painel remoto</dt><dd>{{ $selectedUser['painel'] ? 'Liberado' : 'Sem acesso' }}</dd></div>
                @if ($selectedUser['trocarSenha'])
                    <div><dt>Senha</dt><dd>Aguardando troca da senha inicial</dd></div>
                @endif
                @if ($selectedUser['statusReason'])
                    <div><dt>Motivo da situação atual</dt><dd>{{ $selectedUser['statusReason'] }}</dd></div>
                @endif
            </dl>
        </x-ui.card>

        <div class="user-management-sections">
            <x-ui.card title="Perfil" description="Define o que o colaborador vê e faz no sistema">
                @if ($selectedUser['isSelf'])
                    <p class="user-management-note">Você não pode trocar o próprio perfil.</p>
                @else
                    <form wire:submit="changePerfil" class="user-management-reason-form">
                        <x-ui.select id="novo-perfil" label="Novo perfil" wire:model="novoPerfilId" :error="$errors->first('novoPerfilId')">
                            <option value="">Selecione</option>
                            @foreach ($perfis as $perfil)
                                <option value="{{ $perfil->id }}">{{ $perfil->nome }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button type="submit" variant="secondary">Trocar perfil</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card title="Painel de gestão remoto" description="Arrecadação, caixas e quem entrou no dia, pela internet">
                @if (! $painelLigado)
                    <p class="user-management-note">O painel na nuvem está desligado neste servidor.</p>
                @endif
                <div class="user-management-reason-form">
                    <x-ui.checkbox id="detalhe-painel" label="Acesso ao painel de gestão remoto" :checked="$selectedUser['painel']" wire:click="togglePainel" wire:key="painel-{{ $selectedUser['id'] }}-{{ $selectedUser['painel'] ? 1 : 0 }}" />
                </div>
                @if ($selectedUser['painel'])
                    <form wire:submit="setPainelPassword" class="user-management-reason-form">
                        <x-ui.field id="detalhe-senha-painel" label="Senha do painel remoto" type="password" wire:model="senhaPainel" autocomplete="new-password" help="Mínimo de 10 caracteres." :error="$errors->first('senhaPainel')" />
                        <x-ui.field id="detalhe-senha-painel-repetir" label="Repita a senha" type="password" wire:model="senhaPainel_confirmation" autocomplete="new-password" />
                        <x-ui.button type="submit" variant="secondary">Definir senha do painel</x-ui.button>
                    </form>
                    <p class="user-management-note">Endereço: <a href="{{ $enderecoPainel }}" target="_blank" rel="noopener">{{ $enderecoPainel }}</a> · login com o e-mail {{ $selectedUser['email'] }}.</p>
                @endif
            </x-ui.card>

            <x-ui.card title="Senha do sistema" description="Cria uma nova senha inicial; o colaborador troca no próximo acesso">
                <form wire:submit="resetPassword" class="user-management-reason-form">
                    <x-ui.field id="nova-senha" label="Nova senha inicial" type="password" wire:model="novaSenha" autocomplete="new-password" help="Mínimo de 8 caracteres. As sessões abertas dele caem." :error="$errors->first('novaSenha')" />
                    <x-ui.field id="nova-senha-repetir" label="Repita a senha" type="password" wire:model="novaSenha_confirmation" autocomplete="new-password" />
                    <x-ui.button type="submit" variant="secondary">Redefinir senha</x-ui.button>
                </form>
            </x-ui.card>

            @unless ($selectedUser['isSelf'])
                <x-ui.card title="Derrubar colaborador" description="Tira o colaborador do sistema na hora">
                    <p class="user-management-note">
                        @if ($selectedUser['posto'])
                            Ele está no posto <strong>{{ $selectedUser['posto'] }}</strong>. O posto fica livre e o caixa, se estiver aberto, vai para conferência.
                        @else
                            Todas as sessões dele são encerradas. Ele pode entrar de novo com a própria senha.
                        @endif
                    </p>
                    <form wire:submit="kickUser" class="user-management-reason-form">
                        <x-ui.field id="derrubar-motivo" label="Motivo" wire:model="derrubarMotivo" placeholder="Ex.: esqueceu o sistema aberto na guarita" :error="$errors->first('derrubarMotivo')" required />
                        <x-ui.button type="submit" variant="danger" wire:confirm="Derrubar {{ $selectedUser['name'] }} agora?">Derrubar agora</x-ui.button>
                    </form>
                </x-ui.card>
            @endunless

            <x-ui.card title="Bloqueio e inativação" description="Impede o acesso à conta">
                @if ($selectedUser['isSelf'])
                    <p class="user-management-note">Não é possível bloquear ou inativar a própria conta por aqui.</p>
                @elseif ($selectedUser['isLastAdmin'])
                    <p class="user-management-note">Este usuário não pode ser bloqueado nem inativado enquanto for o único com a permissão de administrar usuários.</p>
                @else
                    @if ($selectedUser['status'] === 'bloqueado')
                        <div class="user-management-reason-form"><x-ui.button variant="success" wire:click="unblockUser">Desbloquear</x-ui.button></div>
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
        </div>
    @endif
</div>

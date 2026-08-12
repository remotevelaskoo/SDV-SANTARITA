<div class="active-sessions">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    <x-ui.card
        title="Sessões ativas"
        description="Dispositivos e navegadores conectados com o seu usuário. Encerre o acesso de qualquer sessão que você não reconheça."
    >
        <x-slot:headerAction>
            <x-ui.button
                variant="secondary"
                size="sm"
                wire:click="revokeOthers"
                :disabled="collect($activeSessions)->where('isCurrent', false)->isEmpty()"
            >
                Encerrar todas as outras sessões
            </x-ui.button>
        </x-slot:headerAction>

        <x-ui.responsive-table
            label="Lista de sessões ativas"
            :state="count($activeSessions) ? 'ready' : 'empty'"
            empty-title="Nenhuma sessão encontrada"
            empty-description="Faça login novamente para iniciar uma nova sessão."
        >
            <x-slot:table>
                <thead><tr><th>Dispositivo / IP</th><th>Última atividade</th><th>Situação</th><th>Ações</th></tr></thead>
                <tbody>
                    @foreach ($activeSessions as $session)
                        <tr>
                            <td><strong>{{ $session['ipAddress'] }}</strong><small>{{ Str::limit($session['userAgent'], 80) }}</small></td>
                            <td>{{ $session['lastActivity'] }}</td>
                            <td>
                                @if ($session['isCurrent'])
                                    <x-ui.badge variant="success">Sessão atual</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral">Outro dispositivo</x-ui.badge>
                                @endif
                            </td>
                            <td>
                                @unless ($session['isCurrent'])
                                    <x-ui.button variant="ghost" size="sm" wire:click="revoke('{{ $session['id'] }}')">Encerrar</x-ui.button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-slot:table>

            <x-slot:cards>
                <ul class="ui-mobile-records">
                    @foreach ($activeSessions as $session)
                        <li>
                            <div>
                                <strong>{{ $session['ipAddress'] }}</strong>
                                <small>{{ Str::limit($session['userAgent'], 60) }}</small>
                            </div>
                            <time>{{ $session['lastActivity'] }}</time>
                            @if ($session['isCurrent'])
                                <x-ui.badge variant="success">Sessão atual</x-ui.badge>
                            @else
                                <x-ui.badge variant="neutral">Outro dispositivo</x-ui.badge>
                                <x-ui.button variant="ghost" size="sm" wire:click="revoke('{{ $session['id'] }}')">Encerrar</x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-slot:cards>
        </x-ui.responsive-table>
    </x-ui.card>
</div>

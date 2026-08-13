<div class="portaria-home">
    <section class="welcome" aria-labelledby="welcome-title">
        <h2 id="welcome-title">Olá, {{ auth()->user()->name }}</h2>
        <p>Resumo do turno e atalhos operacionais da Portaria Principal.</p>
    </section>

    @if (count($alerts))
        <section class="alerts" aria-label="Alertas críticos">
            @foreach ($alerts as $alert)
                <x-ui.alert :variant="$alert['severity']" :title="$alert['title']">
                    {{ $alert['description'] }}
                </x-ui.alert>
            @endforeach
        </section>
    @endif

    <section class="operation-grid" aria-label="Caixa e atalhos">
        <x-ui.card variant="status" title="Situação do caixa" description="{{ $cashRegister['code'] }}">
            <x-slot:headerAction>
                <x-ui.badge :variant="$cashRegister['status'] === 'aberto' ? 'success' : 'neutral'">
                    {{ ucfirst($cashRegister['status']) }}
                </x-ui.badge>
            </x-slot:headerAction>

            <div class="cash-card__status-row">
                <strong class="cash-card__amount">
                    R$ {{ number_format($cashRegister['total'], 2, ',', '.') }}
                </strong>
            </div>

            <ul class="cash-card__meta">
                <li><x-icon name="clock" /> Aberto às {{ $cashRegister['openedAt'] }}</li>
                <li><x-icon name="users" /> Responsável: {{ $cashRegister['openedBy'] }}</li>
            </ul>

            <x-slot:footer>
                <x-ui.button variant="secondary" href="{{ route('cash-register') }}">
                    Ver movimentações
                </x-ui.button>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card title="Atalhos" description="Ações mais usadas pela portaria — provisório, aguardando confirmação da equipe">
            <div class="shortcuts-grid">
                @foreach ($shortcuts as $shortcut)
                    <a
                        class="shortcut-card @if (! $shortcut['route']) shortcut-card--disabled @endif"
                        href="{{ $shortcut['route'] ? route($shortcut['route']) : '#' }}"
                        @if (! $shortcut['route']) aria-disabled="true" tabindex="-1" title="Módulo será portado em uma próxima etapa" @endif
                    >
                        <span class="shortcut-card__icon"><x-icon :name="$shortcut['icon']" /></span>
                        <strong>{{ $shortcut['label'] }}</strong>
                        <p>{{ $shortcut['description'] }}</p>
                        @unless ($shortcut['route'])
                            <span class="shortcut-card__status">Em breve</span>
                        @endunless
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    </section>

    <section aria-label="Atendimentos recentes">
        <x-ui.card title="Atendimentos recentes" description="Últimas interações realizadas nesta portaria">
            <x-ui.responsive-table
                label="Atendimentos recentes"
                :state="count($recentAttendances) ? 'ready' : 'empty'"
                empty-title="Nenhum atendimento registrado"
                empty-description="Os atendimentos aparecem aqui assim que uma entrada ou saída for validada."
            >
                <x-slot:table>
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Pessoa</th>
                            <th>Vínculo</th>
                            <th>Atendimento</th>
                            <th>Resultado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentAttendances as $attendance)
                            <tr>
                                <td class="numeric">{{ $attendance['time'] }}</td>
                                <td><strong>{{ $attendance['name'] }}</strong></td>
                                <td>{{ $attendance['relation'] }}</td>
                                <td>{{ $attendance['subject'] }}</td>
                                <td>
                                    <x-ui.badge :variant="match ($attendance['result']) {
                                        'liberado' => 'success',
                                        'negado' => 'danger',
                                        'pendente' => 'warning',
                                        default => 'info',
                                    }">
                                        {{ ucfirst($attendance['result']) }}
                                    </x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="ui-mobile-records">
                        @foreach ($recentAttendances as $attendance)
                            <li>
                                <div>
                                    <strong>{{ $attendance['name'] }}</strong>
                                    <small>{{ $attendance['relation'] }} · {{ $attendance['subject'] }}</small>
                                </div>
                                <time>{{ $attendance['time'] }}</time>
                                <x-ui.badge :variant="match ($attendance['result']) {
                                    'liberado' => 'success',
                                    'negado' => 'danger',
                                    'pendente' => 'warning',
                                    default => 'info',
                                }">
                                    {{ ucfirst($attendance['result']) }}
                                </x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>
        </x-ui.card>
    </section>

    <section aria-label="Documentos protegidos dos atendimentos recentes">
        @php($comArquivo = collect($recentAttendances)->filter(fn ($a) => $a['protectedFiles']['pre_registration_id']))
        @if ($comArquivo->isEmpty())
            <x-ui.alert variant="info" title="Sem imagens de pré-cadastro aprovado">
                Nenhum dos atendimentos recentes tem documento ou selfie protegido vinculado a um pré-cadastro aprovado.
            </x-ui.alert>
        @else
            @foreach ($comArquivo as $attendance)
                <x-protected-file-review
                    :document-link="$attendance['protectedFiles']['document']"
                    :selfie-link="$attendance['protectedFiles']['selfie']"
                    :id="'portaria-'.$attendance['protectedFiles']['pre_registration_id']"
                    :title="'Conferência visual protegida — '.$attendance['name']"
                />
            @endforeach
        @endif
    </section>
</div>

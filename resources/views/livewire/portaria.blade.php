<div class="portaria-home sdv-one-portaria">
    {{-- Hero e identidade são exclusivamente visuais: os fluxos da portaria permanecem intactos. --}}
    <section class="welcome sdv-one-hero" aria-labelledby="welcome-title">
        <div class="sdv-one-hero__content">
            <span class="sdv-one-hero__eyebrow"><x-icon name="door" /> Operação de acesso</span>
            <h2 id="welcome-title">Olá, {{ auth()->user()->name }}</h2>
            <p>Resumo do turno e atalhos operacionais da Portaria Principal.</p>
        </div>
        <div class="sdv-one-hero__architecture" aria-hidden="true">
            <span class="sdv-one-hero__roof"></span>
            <span class="sdv-one-hero__tower"></span>
            <span class="sdv-one-hero__gate"></span>
            <span class="sdv-one-hero__lights"></span>
        </div>
    </section>

    <section class="operation-grid sdv-one-operation" aria-label="Caixa e atalhos">
        <x-ui.card class="sdv-one-cash-panel" variant="status" title="Situação do caixa" description="{{ $cashRegister['code'] }}">
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
                    Ver movimentações <x-icon name="chevron-right" />
                </x-ui.button>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card class="sdv-one-shortcuts-panel" title="Ações da portaria" description="Principais rotinas do turno. Acesso rápido e seguro.">
            <div class="shortcuts-grid sdv-one-shortcuts">
                @foreach ($shortcuts as $shortcut)
                    @php($shortcutRoute = $shortcut['route'] ?: ($shortcut['label'] === 'Cadastro rápido' ? 'validation' : null))
                    <a
                        class="shortcut-card @if ($loop->first) shortcut-card--primary @endif @if (! $shortcutRoute) shortcut-card--disabled @endif"
                        href="{{ $shortcutRoute ? route($shortcutRoute) : '#' }}"
                        @if (! $shortcutRoute) aria-disabled="true" tabindex="-1" title="Módulo indisponível" @endif
                    >
                        <span class="shortcut-card__icon"><x-icon :name="$shortcut['icon']" /></span>
                        <span class="shortcut-card__copy">
                            <strong>{{ $shortcut['label'] }}</strong>
                            <p>{{ $shortcut['description'] }}</p>
                            @if (! $shortcut['route'] && $shortcutRoute)
                                <span class="shortcut-card__status">Disponível na validação</span>
                            @elseif (! $shortcutRoute)
                                <span class="shortcut-card__status">Em breve</span>
                            @endif
                        </span>
                        <span class="shortcut-card__arrow" aria-hidden="true"><x-icon name="chevron-right" /></span>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    </section>

    <section class="sdv-one-overview" aria-label="Resumo operacional">
        <div class="sdv-one-overview__metrics" aria-label="Indicadores reais do dia">
            <article class="sdv-one-metric sdv-one-metric--entries">
                <span class="sdv-one-metric__icon"><x-icon name="door" /></span>
                <span><strong>{{ $dailySummary['entradas'] }}</strong><small>Entradas hoje</small></span>
            </article>
            <article class="sdv-one-metric sdv-one-metric--exits">
                <span class="sdv-one-metric__icon"><x-icon name="logout" /></span>
                <span><strong>{{ $dailySummary['saidas'] }}</strong><small>Saídas hoje</small></span>
            </article>
            <article class="sdv-one-metric sdv-one-metric--pending">
                <span class="sdv-one-metric__icon"><x-icon name="users" /></span>
                <span><strong>{{ $dailySummary['pendentes'] }}</strong><small>Pré-cadastros pendentes</small></span>
            </article>
        </div>

        @if (count($alerts))
            <div class="alerts sdv-one-overview__alerts" aria-label="Alertas críticos">
                @foreach ($alerts as $alert)
                    <x-ui.alert :variant="$alert['severity']" :title="$alert['title']">
                        {{ $alert['description'] }}
                        @if (auth()->user()?->hasPermission('pre-registro.analisar'))
                            <a class="sdv-one-alert-link" href="{{ route('pre-registrations') }}">Analisar agora <x-icon name="chevron-right" /></a>
                        @endif
                    </x-ui.alert>
                @endforeach
            </div>
        @else
            <div class="sdv-one-overview__all-clear" role="status">
                <x-icon name="check-circle" />
                <span><strong>Nenhum alerta crítico</strong><small>Sem pré-cadastros atrasados há mais de 24 horas.</small></span>
            </div>
        @endif
    </section>

    <section class="sdv-one-recent" aria-label="Atendimentos recentes">
        <x-ui.card class="sdv-one-recent__card" title="Últimas movimentações" description="Atendimentos registrados nesta portaria em tempo real">
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


    @if (auth()->user()?->hasPermission('acessos.liberar-reles-do-posto'))
        <section aria-label="Liberação manual">
            <livewire:liberacao-manual-portaria />
        </section>
    @endif


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
                    :document-back-link="$attendance['protectedFiles']['document_back']"
                    :document-portrait-link="$attendance['protectedFiles']['document_portrait']"
                    :selfie-link="$attendance['protectedFiles']['selfie']"
                    :id="'portaria-'.$attendance['protectedFiles']['pre_registration_id']"
                    :title="'Conferência visual protegida — '.$attendance['name']"
                />
            @endforeach
        @endif
    </section>
</div>

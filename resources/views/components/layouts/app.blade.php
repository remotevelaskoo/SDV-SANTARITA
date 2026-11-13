@php
    // Preferência expandida/compacta por usuário e implantação neste navegador
    // (AJ-009). A chave é um resumo irreversível: nenhum dado pessoal ou
    // permissão vai para o localStorage.
    $navKey = auth()->check()
        ? 'sdv.menu.'.substr(hash_hmac('sha256', auth()->id().'|'.rescue(fn () => \App\Support\ImplantacaoContext::current()->id, '', false), (string) config('app.key')), 0, 20)
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-sidebar="expanded" @if ($navKey) data-nav-key="{{ $navKey }}" @endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Aplica a preferência antes da primeira pintura, sem salto de layout. --}}
        <script>(function(){var d=document.documentElement,k=d.getAttribute('data-nav-key');try{if(k&&window.localStorage.getItem(k)==='compact'){d.setAttribute('data-sidebar','compact');}}catch(e){}})();</script>
        <meta name="description" content="Indicadores de acesso, movimentações recentes e alertas críticos do controle de acesso do condomínio Santa Rita.">

        <title>{{ $title ?? config('app.name') }} — SDV One · Santa Rita</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo</a>

        <div class="app-shell">
            <aside class="sidebar" id="barra-lateral" aria-label="Navegação principal" @if ($navKey) data-nav-key="{{ $navKey }}" @endif>
                <div class="sidebar__brand-row">
                    <a class="brand" href="{{ route('home') }}" aria-label="SDV One — Início">
                        <x-sdv-one-logo />
                    </a>
                </div>

                <button
                    class="sidebar__toggle"
                    type="button"
                    aria-controls="barra-lateral"
                    aria-expanded="true"
                    aria-label="Recolher menu"
                    data-sidebar-toggle
                >
                    <x-icon name="panel-left" />
                    <span class="sidebar__toggle-text" aria-hidden="true">Recolher menu</span>
                </button>

                <x-navigation context="sidebar" />

                <div class="sidebar__footer">
                    @auth
                        <div>
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>{{ auth()->user()->operationalRoleLabel() }}</small>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit">
                                <x-icon name="logout" />
                                <span>Sair</span>
                            </button>
                        </form>
                    @else
                        <div>
                            <strong>Visitante</strong>
                            <small>Não autenticado</small>
                        </div>
                        <a href="{{ route('login') }}">
                            <x-icon name="logout" />
                            <span>Entrar</span>
                        </a>
                    @endauth
                </div>
            </aside>

            <div class="workspace">
                <header class="operational-header">
                    <div class="operational-header__main">
                        <button
                            class="header-icon mobile-drawer-trigger"
                            type="button"
                            aria-label="Abrir menu"
                            aria-controls="gaveta-navegacao"
                            aria-expanded="false"
                            data-drawer-open
                        >
                            <x-icon name="menu" />
                        </button>

                        <div class="operational-header__title">
                            <span class="operational-header__title-icon" aria-hidden="true"><x-icon name="building" /></span>
                            <div class="operational-header__title-copy">
                                <h1>{{ $heading ?? $title ?? config('app.name') }}</h1>
                                <p>{{ $headingDescription ?? 'Operador de portaria · Portaria Principal' }}</p>
                            </div>
                        </div>

                        <div class="operational-header__actions">
                            @php
                                $turnoAtivo = auth()->check()
                                    ? rescue(fn () => app(\App\Integracoes\Aplicacao\TurnosOperacionais::class)->turnoAtivo(auth()->user()), null, false)
                                    : null;
                            @endphp
                            @php($headerState = \App\Support\OperationalHeaderState::forUser(auth()->user()))
                            @if ($turnoAtivo)
                                <a class="cash-status posto-ativo" href="{{ route('posto.selecionar') }}" title="Trocar posto"><x-icon name="door" /> Posto: {{ $turnoAtivo->posto->nome }}</a>
                            @endif
                            @if ($headerState['cash'] !== null)
                                <span @class(['cash-status', 'cash-status--closed' => ! $headerState['cash']['open']]) data-sdv-cash-status><x-icon name="wallet" /> {{ $headerState['cash']['label'] }}</span>
                            @endif
                            <time class="session-time" datetime="{{ now()->toIso8601String() }}">
                                <x-icon name="clock" /> {{ now()->format('d/m/Y H:i') }}
                            </time>

                            <details class="header-popover notifications">
                                <summary class="header-icon" aria-label="Notificações{{ ($headerState['pendingPreRegistrations'] ?? 0) > 0 ? ' — '.$headerState['pendingPreRegistrations'].' pré-cadastros aguardando análise' : '' }}">
                                    <x-icon name="bell" />
                                    @if (($headerState['pendingPreRegistrations'] ?? 0) > 0)
                                        <span class="notification-dot" aria-hidden="true"></span>
                                    @endif
                                </summary>
                                <div class="header-popover__panel notifications__panel">
                                    <strong>Notificações</strong>
                                    <ul>
                                        @if (($headerState['pendingPreRegistrations'] ?? 0) > 0)
                                            <li>
                                                <a class="sdv-one-notification-link" href="{{ route('pre-registrations') }}">
                                                    {{ $headerState['pendingPreRegistrations'] }} {{ $headerState['pendingPreRegistrations'] === 1 ? 'pré-cadastro aguardando análise' : 'pré-cadastros aguardando análise' }}
                                                    <small>Abrir solicitações de visitantes e prestadores</small>
                                                </a>
                                            </li>
                                        @else
                                            <li class="is-read"><span>Nenhuma notificação pendente<small>Não há alertas disponíveis para este perfil.</small></span></li>
                                        @endif
                                    </ul>
                                </div>
                            </details>

                            @auth
                                <details class="header-popover user-menu">
                                    <summary class="user-chip">
                                        <span class="avatar" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                                        <span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->operationalRoleLabel() }}</small></span>
                                    </summary>
                                    <div class="header-popover__panel user-menu__panel">
                                        <strong>{{ auth()->user()->name }}</strong>
                                        <small>{{ auth()->user()->operationalRoleLabel() }} · {{ $turnoAtivo?->posto?->nome ?? 'Sem posto ativo' }}</small>
                                        <span>{{ rescue(fn () => \App\Support\ImplantacaoContext::current()->nome, 'Implantação não definida', false) }}</span>
                                        <a href="{{ route('sessions') }}" class="user-menu__link">
                                            <x-icon name="shield" />
                                            <span>Sessões ativas</span>
                                        </a>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="user-menu__logout">
                                                <x-icon name="logout" />
                                                <span>Sair</span>
                                            </button>
                                        </form>
                                    </div>
                                </details>
                            @endauth
                        </div>
                    </div>

                    <div class="global-search-row">
                        @auth
                            <livewire:busca-global />
                        @endauth
                    </div>
                </header>

                <main id="conteudo-principal" class="main-content" tabindex="-1">
                    @if (session('erro'))
                        <div class="alert alert--danger" role="alert">
                            <x-icon name="alert" />
                            <div>
                                <h3>{{ session('erro_titulo', 'Sem permissão') }}</h3>
                                <p>{{ session('erro') }}</p>
                            </div>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Gaveta do celular: fora do .app-shell para que o fundo fique inerte enquanto aberta. --}}
        <div class="mobile-drawer" id="gaveta-navegacao" hidden data-drawer>
            <div class="mobile-drawer__backdrop" data-drawer-close></div>
            <div class="mobile-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="gaveta-navegacao-titulo" tabindex="-1">
                <div class="mobile-drawer__header">
                    <div class="brand mobile-brand">
                        <span id="gaveta-navegacao-titulo" class="sr-only">Menu SDV One</span>
                        <x-sdv-one-logo />
                    </div>
                    <button class="mobile-drawer__close" type="button" aria-label="Fechar menu" data-drawer-close>
                        <x-icon name="x" />
                    </button>
                </div>
                <x-navigation context="drawer" />
            </div>
        </div>

        <div class="navigation-tooltip" role="tooltip" id="navegacao-dica" hidden data-nav-tooltip></div>

        @livewireScripts
    </body>
</html>

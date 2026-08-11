<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Indicadores de acesso, movimentações recentes e alertas críticos do controle de acesso do condomínio Santa Rita.">

        <title>{{ $title ?? config('app.name') }} — SDV Access Santa Rita</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo</a>

        <div class="app-shell">
            <aside class="sidebar" aria-label="Navegação principal">
                <div class="sidebar__brand-row">
                    <a class="brand" href="{{ route('dashboard') }}" aria-label="SDV Access Santa Rita — Dashboard">
                        <span class="brand__mark" aria-hidden="true">SDV</span>
                        <span>
                            <strong>SDV Access</strong>
                            <small>Santa Rita</small>
                        </span>
                    </a>
                    <button class="sidebar__collapse" type="button" aria-label="Recolher navegação" disabled>
                        <x-icon name="chevrons-left" />
                    </button>
                </div>

                <x-navigation />

                <div class="sidebar__footer">
                    @auth
                        <div>
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>Operador de portaria</small>
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
                        <details class="mobile-navigation">
                            <summary aria-label="Abrir navegação"><x-icon name="menu" /></summary>
                            <div class="mobile-navigation__panel">
                                <div class="brand mobile-brand">
                                    <span class="brand__mark" aria-hidden="true">SDV</span>
                                    <span><strong>SDV Access</strong><small>Santa Rita</small></span>
                                </div>
                                <x-navigation />
                            </div>
                        </details>

                        <button class="header-icon header-icon--desktop" type="button" aria-label="Recolher ou abrir navegação" disabled>
                            <x-icon name="panel-left" />
                        </button>

                        <div class="operational-header__title">
                            <h1>{{ $heading ?? $title ?? config('app.name') }}</h1>
                            <p>{{ $headingDescription ?? 'Operador de portaria · Portaria Principal' }}</p>
                        </div>

                        <div class="operational-header__actions">
                            <span class="cash-status"><x-icon name="wallet" /> Caixa 01 aberto às 13:00</span>
                            <time class="session-time" datetime="{{ now()->toIso8601String() }}">
                                <x-icon name="clock" /> {{ now()->format('d/m/Y H:i') }}
                            </time>

                            <details class="header-popover notifications">
                                <summary class="header-icon" aria-label="Notificações">
                                    <x-icon name="bell" />
                                    <span class="notification-dot" aria-hidden="true"></span>
                                </summary>
                                <div class="header-popover__panel notifications__panel">
                                    <strong>Notificações</strong>
                                    <ul>
                                        <li><span>Pré-cadastro recebido<small>Visitante para Bloco B — Apto 304</small></span><time>15:57</time></li>
                                        <li><span>Vínculo de inquilino encerrado<small>Bloco C — Apto 501</small></span><time>15:12</time></li>
                                        <li class="is-read"><span>Caixa aberto<small>Guarita — Caixa 01</small></span><time>13:00</time></li>
                                    </ul>
                                </div>
                            </details>

                            @auth
                                <details class="header-popover user-menu">
                                    <summary class="user-chip">
                                        <span class="avatar" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                                        <span><strong>{{ auth()->user()->name }}</strong><small>Operador de portaria</small></span>
                                    </summary>
                                    <div class="header-popover__panel user-menu__panel">
                                        <strong>{{ auth()->user()->name }}</strong>
                                        <small>Operador de portaria · Portaria Principal</small>
                                        <span>Condomínio Santa Rita</span>
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
                        <label class="global-search">
                            <span class="sr-only">Busca global</span>
                            <x-icon name="search" />
                            <input type="search" placeholder="Buscar pessoa, documento, imóvel, placa ou protocolo" disabled>
                        </label>
                    </div>
                </header>

                <main id="conteudo-principal" class="main-content" tabindex="-1">
                    @if (session('erro'))
                        <div class="alert alert--danger" role="alert">
                            <x-icon name="alert" />
                            <div>
                                <h3>Sem permissão</h3>
                                <p>{{ session('erro') }}</p>
                            </div>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>

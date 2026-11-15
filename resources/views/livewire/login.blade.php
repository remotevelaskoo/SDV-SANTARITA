<div class="login-shell">
    <section class="login-presentation" aria-labelledby="presentation-title">
        <a class="login-brand" href="{{ route('login') }}" aria-label="SDV One · Santa Rita — Entrar">
            <x-sdv-one-logo class="sdv-one-logo--login" />
        </a>

        <div class="login-presentation__content">
            <span class="login-kicker">Controle de acesso inteligente</span>
            <h1 id="presentation-title">Segurança começa com informação confiável.</h1>
            <p>
                Pessoas, veículos e autorizações conectados ao imóvel para uma operação
                mais rápida na portaria e mais segura para o condomínio.
            </p>

            <ul class="login-benefits" aria-label="Benefícios da plataforma">
                <li><x-icon name="shield" /> Trilha de auditoria</li>
                <li><x-icon name="users" /> Perfis e permissões</li>
            </ul>
        </div>

        <p class="login-presentation__footer">SDV One · Condomínio Santa Rita</p>
    </section>

    <section class="login-access" aria-labelledby="login-title">
        <div class="login-card">
            <div class="login-brand login-brand--mobile" aria-hidden="true">
                <x-sdv-one-logo class="sdv-one-logo--on-light" />
            </div>

            <span class="demo-label">Ambiente de demonstração</span>
            <h2 id="login-title">Entrar no sistema</h2>
            <p class="login-card__intro">Informe sua identificação operacional e sua senha.</p>

            @if (session('sessao_encerrada'))
                <div class="login-error" role="alert">
                    <x-icon name="alert" />
                    <div>
                        <strong>Sua sessão foi encerrada</strong>
                        <span>{{ session('sessao_encerrada') }}</span>
                    </div>
                </div>
            @endif

            @if ($errors->has('credentials'))
                <div class="login-error" role="alert">
                    <x-icon name="alert" />
                    <div>
                        <strong>Não foi possível entrar</strong>
                        <span>{{ $errors->first('credentials') }}</span>
                    </div>
                </div>
            @endif

            <form wire:submit="login" class="login-form">
                <label class="form-field">
                    <span>Identificação</span>
                    <input
                        type="text"
                        wire:model="identification"
                        name="identification"
                        autocomplete="username"
                        placeholder="Ex.: portaria"
                        autofocus
                    >
                    @error('identification') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="form-field" x-data="{ showPassword: false }">
                    <span>Senha</span>
                    <span class="password-control">
                        <input
                            x-bind:type="showPassword ? 'text' : 'password'"
                            wire:model="password"
                            name="password"
                            autocomplete="current-password"
                        >
                        <button
                            type="button"
                            x-on:click="showPassword = ! showPassword"
                            x-text="showPassword ? 'Ocultar' : 'Mostrar'"
                            x-bind:aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                        >Mostrar</button>
                    </span>
                    @error('password') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <button class="login-submit" type="submit" wire:loading.attr="disabled" wire:target="login">
                    <span wire:loading.remove wire:target="login">Entrar</span>
                    <span wire:loading wire:target="login">Entrando…</span>
                </button>
            </form>

            <div class="demo-access">
                <div>
                    <strong>Acesso para testar</strong>
                    <p><b>portaria</b> — Porteiro/Caixa</p>
                    <p><b>portaria.leitura</b> — Auditor</p>
                    <p><b>gestor</b> — Gestor</p>
                    <p><b>administrador</b> — Administrador</p>
                    <p><b>administrador.multi</b> — Administrador em 2 implantações</p>
                    <p>Senha para todas: <b>sdv2026</b></p>
                </div>
                <button type="button" wire:click="useDemoAccount">Preencher portaria</button>
            </div>

            <a class="forgot-password" href="{{ route('password.request') }}">Esqueci minha senha</a>
            <p class="login-support">Problemas para acessar? Procure o administrador do sistema.</p>

            <footer class="login-maker">
                <span>Uma solução</span>
                <img src="{{ asset('images/solucoes-do-vale.png') }}" alt="Soluções do Vale — Tecnologia, Soluções, Segurança" width="220" height="42" loading="lazy">
            </footer>
        </div>
    </section>
</div>

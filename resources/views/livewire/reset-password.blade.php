<div class="login-access" aria-labelledby="reset-title">
    <div class="login-card">
        <h2 id="reset-title">Redefinir senha</h2>

        @if ($completed)
            <div class="login-success" role="status">
                <x-icon name="check-circle" />
                <div>
                    <strong>Senha redefinida</strong>
                    <span>Sua senha foi alterada. Você já pode entrar com a nova senha.</span>
                </div>
            </div>
            <p class="login-support"><a href="{{ route('login') }}">Ir para o login</a></p>
        @else
            <p class="login-card__intro">Escolha uma nova senha para sua conta.</p>

            @if ($errors->has('token'))
                <div class="login-error" role="alert">
                    <x-icon name="alert" />
                    <div>
                        <strong>Não foi possível redefinir</strong>
                        <span>{{ $errors->first('token') }}</span>
                    </div>
                </div>
            @endif

            <form wire:submit="resetPassword" class="login-form">
                <label class="form-field" x-data="{ showPassword: false }">
                    <span>Nova senha</span>
                    <span class="password-control">
                        <input
                            x-bind:type="showPassword ? 'text' : 'password'"
                            wire:model="password"
                            name="password"
                            autocomplete="new-password"
                            autofocus
                        >
                        <button
                            type="button"
                            x-on:click="showPassword = ! showPassword"
                            x-text="showPassword ? 'Ocultar' : 'Mostrar'"
                            x-bind:aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                        >Mostrar</button>
                    </span>
                    <small class="field-hint">Mínimo de 8 caracteres.</small>
                    @error('password') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="form-field">
                    <span>Confirmar nova senha</span>
                    <input type="password" wire:model="password_confirmation" name="password_confirmation" autocomplete="new-password">
                </label>

                <button class="login-submit" type="submit" wire:loading.attr="disabled" wire:target="resetPassword">
                    <span wire:loading.remove wire:target="resetPassword">Redefinir senha</span>
                    <span wire:loading wire:target="resetPassword">Redefinindo…</span>
                </button>
            </form>
        @endif
    </div>
</div>

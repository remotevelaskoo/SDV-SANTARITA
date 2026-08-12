<div class="login-access" aria-labelledby="forgot-title">
    <div class="login-card">
        <h2 id="forgot-title">Recuperar acesso</h2>
        <p class="login-card__intro">Informe sua identificação operacional. Se ela existir, enviaremos um link de redefinição de senha.</p>

        @if ($linkSent)
            <div class="login-success" role="status">
                <x-icon name="check-circle" />
                <div>
                    <strong>Verifique seu e-mail cadastrado</strong>
                    <span>Se a identificação informada existir, um link de recuperação foi enviado. O link expira em {{ config('auth.passwords.users.expire') }} minutos.</span>
                </div>
            </div>
        @else
            @if ($errors->has('identification'))
                <div class="login-error" role="alert">
                    <x-icon name="alert" />
                    <div>
                        <strong>Não foi possível continuar</strong>
                        <span>{{ $errors->first('identification') }}</span>
                    </div>
                </div>
            @endif

            <form wire:submit="sendResetLink" class="login-form">
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
                </label>

                <button class="login-submit" type="submit" wire:loading.attr="disabled" wire:target="sendResetLink">
                    <span wire:loading.remove wire:target="sendResetLink">Enviar link de recuperação</span>
                    <span wire:loading wire:target="sendResetLink">Enviando…</span>
                </button>
            </form>
        @endif

        <p class="login-support"><a href="{{ route('login') }}">Voltar para o login</a></p>
    </div>
</div>

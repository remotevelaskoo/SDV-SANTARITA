<div class="login-access" aria-labelledby="trocar-senha-title">
    <div class="login-card">
        <h2 id="trocar-senha-title">Crie a sua senha</h2>
        <p class="login-card__intro">Olá, {{ auth()->user()?->name }}. Você entrou com a senha inicial que o administrador criou. Escolha agora a sua senha pessoal.</p>

        <form wire:submit="salvar" class="login-form">
            <label class="form-field" x-data="{ showPassword: false }">
                <span>Nova senha</span>
                <span class="password-control">
                    <input x-bind:type="showPassword ? 'text' : 'password'" wire:model="password" name="password" autocomplete="new-password" autofocus>
                    <button type="button" x-on:click="showPassword = ! showPassword" x-text="showPassword ? 'Ocultar' : 'Mostrar'" x-bind:aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'">Mostrar</button>
                </span>
                <small class="field-hint">Mínimo de 8 caracteres.</small>
                @error('password') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="form-field">
                <span>Confirmar nova senha</span>
                <input type="password" wire:model="password_confirmation" name="password_confirmation" autocomplete="new-password">
            </label>

            <button class="login-submit" type="submit" wire:loading.attr="disabled" wire:target="salvar">
                <span wire:loading.remove wire:target="salvar">Salvar e entrar</span>
                <span wire:loading wire:target="salvar">Salvando…</span>
            </button>
        </form>
    </div>
</div>

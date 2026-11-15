<div>
    @if ($ativo)
        <section class="panel acesso-painel-remoto-wrap" aria-labelledby="painel-remoto-title">
            <header class="panel__header">
                <div>
                    <h2 id="painel-remoto-title">Painel de gestão fora do condomínio</h2>
                    <p>Veja arrecadação, acessos, cadastros e caixas do dia pela internet. Nomes, CPFs e fotos não saem do condomínio.</p>
                </div>
                <x-ui.badge variant="neutral">Atualiza a cada 5 min</x-ui.badge>
            </header>

            <p>Endereço: <a href="{{ $endereco }}" target="_blank" rel="noopener">{{ $endereco }}</a> · entre com o e-mail <strong>{{ $email }}</strong>.</p>

            @if ($mensagem)
                <x-ui.alert variant="success" title="Pronto">{{ $mensagem }}</x-ui.alert>
            @endif
            @if ($erro)
                <x-ui.alert variant="danger" title="Acesso não criado">{{ $erro }}</x-ui.alert>
            @endif

            <form wire:submit="salvar" class="acesso-painel-remoto">
                <x-ui.field id="painel-remoto-senha" label="Senha do painel remoto" type="password" wire:model="senha" autocomplete="new-password" help="Mínimo de 10 caracteres. Use uma senha diferente da do SDV." :error="$errors->first('senha')" />
                <x-ui.field id="painel-remoto-repetir" label="Repita a senha" type="password" wire:model="senha_confirmation" autocomplete="new-password" />
                <x-ui.field id="painel-remoto-atual" label="Sua senha do SDV (para confirmar)" type="password" wire:model="senhaAtual" autocomplete="current-password" :error="$errors->first('senhaAtual')" />
                <footer>
                    <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="salvar">Criar ou trocar senha do painel</x-ui.button>
                </footer>
            </form>
        </section>
    @endif
</div>

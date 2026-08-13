<div class="implantacao-selection">
    <div class="implantacao-selection__card">
        <div class="login-brand login-brand--mobile" aria-hidden="true">
            <span class="brand__mark">SDV</span>
            <span><strong>SDV Access</strong><small>Santa Rita</small></span>
        </div>

        <h1>Selecionar implantação</h1>
        <p>Sua conta tem acesso a mais de uma implantação. Escolha em qual você quer trabalhar agora.</p>

        @if ($erro)
            <x-ui.alert variant="danger" title="Seleção inválida">{{ $erro }}</x-ui.alert>
        @endif

        <ul class="implantacao-selection__list">
            @foreach ($implantacoes as $implantacao)
                <li>
                    <button type="button" wire:click="selecionar('{{ $implantacao['id'] }}')">
                        <strong>{{ $implantacao['nome'] }}</strong>
                        <small>{{ $implantacao['organizacao'] }}</small>
                    </button>
                </li>
            @endforeach
        </ul>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="implantacao-selection__logout">Sair</button>
        </form>
    </div>
</div>

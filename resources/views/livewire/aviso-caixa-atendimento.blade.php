<div>
    @if ($mostrar)
        <section class="validation-feedback" aria-label="Caixa do posto">
            @if ($acabouDeAbrir)
                <x-ui.alert variant="success" title="Caixa aberto">
                    Saldo inicial de R$ {{ $saldoInicial }} registrado{{ $posto ? ' no posto '.$posto->nome : '' }}. A liberação de entrada está disponível.
                </x-ui.alert>
            @elseif ($posto === null)
                <x-ui.alert variant="warning" title="Seu caixa está fechado">
                    Para liberar entradas, assuma o seu posto e abra o caixa. Sem caixa aberto, a portaria só consulta e salva sem liberar.
                    <x-slot:action>
                        <x-ui.button variant="primary" :href="route('posto.selecionar', ['voltar' => '/validacao'])">Escolher o posto</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="dispensar">Só consultar</x-ui.button>
                    </x-slot:action>
                </x-ui.alert>
            @else
                <x-ui.alert variant="warning" title="Seu caixa está fechado">
                    Para liberar entradas, abra o caixa do posto {{ $posto->nome }}. Sem caixa aberto, a portaria só consulta e salva sem liberar.
                    <x-slot:action>
                        <form wire:submit="abrir" class="cash-register-form-fields">
                            <x-ui.field id="aviso-caixa-saldo-inicial" label="Saldo inicial" wire:model="saldoInicial" placeholder="0,00" :error="$errors->first('saldoInicial')" required />
                            <x-ui.button type="submit" variant="success" wire:loading.attr="disabled" wire:target="abrir">Abrir caixa</x-ui.button>
                            <x-ui.button type="button" variant="ghost" wire:click="dispensar">Só consultar</x-ui.button>
                        </form>
                    </x-slot:action>
                </x-ui.alert>
            @endif
        </section>
    @endif
</div>

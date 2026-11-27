<div>
    @if ($mostrar)
        <section class="validation-feedback" aria-label="Caixa do posto">
            @if ($abertoNoPosto !== null)
                <x-ui.alert variant="success" title="Caixa aberto">
                    Saldo inicial de R$ {{ $saldoInicial }} registrado no posto {{ $abertoNoPosto }}. A liberação de entrada está disponível.
                </x-ui.alert>
            @elseif ($postos->isEmpty())
                <x-ui.alert variant="warning" title="Seu caixa está fechado">
                    Nenhum posto livre agora para abrir o caixa. Peça ao Gestor para liberar um posto. Sem caixa aberto, a portaria só consulta e salva sem liberar.
                    <x-slot:action>
                        <x-ui.button variant="ghost" wire:click="dispensar">Só consultar</x-ui.button>
                    </x-slot:action>
                </x-ui.alert>
            @else
                {{-- Regra do PO (26/11/2026): abrir o caixa sempre pergunta o posto. --}}
                <x-ui.alert variant="warning" title="Seu caixa está fechado">
                    Para liberar entradas, confirme o posto em que você está e abra o caixa. Sem caixa aberto, a portaria só consulta e salva sem liberar.
                    <x-slot:action>
                        <form wire:submit="abrir" class="cash-register-form-fields">
                            <x-ui.select id="aviso-caixa-posto" label="Posto deste caixa" wire:model="postoAbertura" :error="$errors->first('postoAbertura')" required>
                                <option value="">Escolha o posto</option>
                                @foreach ($postos as $postoOpcao)
                                    <option value="{{ $postoOpcao->id }}">{{ $postoOpcao->nome }}{{ $postoOpcao->id === $postoAtual ? ' (posto atual)' : '' }}</option>
                                @endforeach
                            </x-ui.select>
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

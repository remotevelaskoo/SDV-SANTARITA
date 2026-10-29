<div class="cash-register">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($status === 'aberto')
        <section class="cash-register-grid">
            <x-ui.cash-summary
                class="cash-register-summary"
                :operator="$operator"
                :terminal="$terminal"
                :opened-at="$openedAt"
                :opening-balance="'R$ '.number_format($openingBalance, 2, ',', '.')"
                :income="'R$ '.number_format($this->incomeTotal(), 2, ',', '.')"
                :expenses="'R$ '.number_format($this->outflowTotal(), 2, ',', '.')"
                :cancellations="'R$ '.number_format($this->cancellationsTotal(), 2, ',', '.')"
                :expected="'R$ '.number_format($this->expectedBalance(), 2, ',', '.')"
                informed="A conferir no fechamento"
                difference="—"
            >
                <x-slot:actions>
                    <x-ui.modal id="close-register-modal" title="Fechar caixa" description="Confira o total antes de concluir o fechamento." trigger-label="Fechar caixa" trigger-variant="danger">
                        <dl class="cash-register-breakdown">
                            <div><dt>Saldo inicial</dt><dd>R$ {{ number_format($openingBalance, 2, ',', '.') }}</dd></div>
                            <div><dt>+ Contribuições pagas ({{ $resumo['quantidades']['pagantes'] }})</dt><dd>R$ {{ number_format($resumo['contribuicoes'], 2, ',', '.') }}</dd></div>
                            <div><dt>+ Outras entradas</dt><dd>R$ {{ number_format($resumo['outras'], 2, ',', '.') }}</dd></div>
                            <div><dt>− Saídas</dt><dd>R$ {{ number_format($resumo['saidas'], 2, ',', '.') }}</dd></div>
                            <div><dt>− Estornos</dt><dd>R$ {{ number_format($resumo['estornos'], 2, ',', '.') }}</dd></div>
                            <div class="cash-register-breakdown__total"><dt>= Total esperado</dt><dd>R$ {{ number_format($this->expectedBalance(), 2, ',', '.') }}</dd></div>
                        </dl>
                        <p class="cash-register-breakdown__nota">Isentos: {{ $resumo['quantidades']['isentos'] }} · Não contribuintes: {{ $resumo['quantidades']['nao_contribuintes'] }} — não entram no valor.</p>
                        <div class="cash-register-form-fields">
                            <x-ui.field id="informed-amount" label="Total contado" wire:model="informedAmount" placeholder="0,00" :error="$errors->first('informedAmount')" required />
                        </div>
                        <label class="cash-register-notes-field" for="closing-notes">
                            <span>Justificativa (obrigatória se houver diferença)</span>
                            <textarea id="closing-notes" wire:model="closingNotes" maxlength="500" rows="3" placeholder="Explique a diferença entre o contado e o esperado…"></textarea>
                        </label>
                        <x-slot:confirm><form method="dialog"><x-ui.button type="submit" variant="danger" wire:click="closeRegister">Confirmar fechamento</x-ui.button></form></x-slot:confirm>
                    </x-ui.modal>
                </x-slot:actions>
            </x-ui.cash-summary>

            <article class="cash-register-movement-card">
                <header><div><h3>Registrar movimentação</h3><p>Entradas, saídas e estornos manuais deste caixa.</p></div></header>
                <div class="cash-register-form-fields">
                    <x-ui.select id="movement-type" label="Tipo" wire:model="movementType" :error="$errors->first('movementType')" required>
                        <option value="entrada">Entrada</option>
                        <option value="saida">Saída</option>
                        <option value="estorno">Estorno</option>
                    </x-ui.select>
                    <x-ui.field id="movement-amount" label="Valor" wire:model="movementAmount" placeholder="0,00" :error="$errors->first('movementAmount')" required />
                    <x-ui.select id="movement-method" label="Forma" wire:model="movementMethod" :error="$errors->first('movementMethod')" required>
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão</option>
                    </x-ui.select>
                    <x-ui.field id="movement-description" label="Descrição" wire:model="movementDescription" placeholder="Ex.: Troco para portão de serviço" :error="$errors->first('movementDescription')" required />
                </div>
                <x-ui.action-group>
                    <x-ui.button variant="primary" wire:click="registerMovement">Registrar movimentação</x-ui.button>
                </x-ui.action-group>
            </article>
        </section>

        <section class="cash-register-movements-card" aria-labelledby="cash-register-movements-title">
            <header><div><h2 id="cash-register-movements-title">Movimentações do turno</h2><p>Contribuições capturadas na Validação de entrada e lançamentos manuais.</p></div></header>

            <x-ui.responsive-table
                label="Movimentações do caixa"
                :state="count($movements) ? 'ready' : 'empty'"
                empty-title="Nenhuma movimentação registrada"
                empty-description="As contribuições e lançamentos manuais aparecerão aqui."
            >
                <x-slot:table>
                    <thead><tr><th>Hora</th><th>Tipo</th><th>Descrição</th><th>Forma</th><th>Valor</th><th>Operador</th></tr></thead>
                    <tbody>
                        @foreach (array_reverse($movements) as $movement)
                            <tr>
                                <td class="numeric">{{ $movement['time'] }}</td>
                                <td><x-ui.badge :variant="match ($movement['type']) { 'entrada' => 'success', 'estorno' => 'warning', default => 'danger' }">{{ match ($movement['type']) { 'entrada' => 'Entrada', 'estorno' => 'Estorno', default => 'Saída' } }}</x-ui.badge></td>
                                <td><strong>{{ $movement['description'] }}</strong>@if ($movement['protocol'])<small>{{ $movement['protocol'] }}</small>@endif</td>
                                <td>{{ match ($movement['method']) { 'pix' => 'PIX', 'cartao' => 'Cartão', default => 'Dinheiro' } }}</td>
                                <td class="numeric">R$ {{ number_format($movement['amount'], 2, ',', '.') }}</td>
                                <td>{{ $movement['operator'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="ui-mobile-records">
                        @foreach (array_reverse($movements) as $movement)
                            <li>
                                <div>
                                    <strong>{{ $movement['description'] }}</strong>
                                    <small>{{ match ($movement['method']) { 'pix' => 'PIX', 'cartao' => 'Cartão', default => 'Dinheiro' } }} · {{ $movement['operator'] }}</small>
                                </div>
                                <time>{{ $movement['time'] }}</time>
                                <x-ui.badge :variant="match ($movement['type']) { 'entrada' => 'success', 'estorno' => 'warning', default => 'danger' }">R$ {{ number_format($movement['amount'], 2, ',', '.') }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>
        </section>
    @else
        <article class="cash-register-closed-card">
            <span class="cash-register-closed-card__icon"><x-icon name="wallet" /></span>
            <strong>Caixa fechado</strong>
            <p>Nenhuma movimentação pode ser registrada até a abertura de um novo turno (RN-084).</p>
            <x-ui.modal id="open-register-modal" title="Abrir caixa" description="Informe o saldo inicial para iniciar o turno." trigger-label="Abrir caixa" trigger-variant="success">
                <div class="cash-register-form-fields">
                    <x-ui.field id="opening-balance" label="Saldo inicial" wire:model="openingBalanceInput" placeholder="0,00" :error="$errors->first('openingBalanceInput')" required />
                </div>
                <x-slot:confirm><form method="dialog"><x-ui.button type="submit" variant="success" wire:click="openRegister">Confirmar abertura</x-ui.button></form></x-slot:confirm>
            </x-ui.modal>
        </article>
    @endif

    <section class="cash-register-history-card" aria-labelledby="cash-register-history-title">
        <header><div><h2 id="cash-register-history-title">Histórico de caixas</h2><p>Turnos fechados — registros não podem ser editados (RN-048).</p></div></header>

        <x-ui.responsive-table
            label="Histórico de caixas fechados"
            :state="count($closedSessions) ? 'ready' : 'empty'"
            empty-title="Nenhum caixa fechado ainda"
            empty-description="O histórico aparecerá aqui após o primeiro fechamento."
        >
            <x-slot:table>
                <thead><tr><th>Período</th><th>Operador</th><th>Saldo inicial</th><th>Esperado</th><th>Contado</th><th>Diferença</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr></thead>
                <tbody>
                    @foreach (array_reverse($closedSessions) as $session)
                        <tr>
                            <td>{{ $session['period'] }}@if ($session['numero'])<small>{{ $session['numero'] }}</small>@endif</td>
                            <td>{{ $session['operator'] }}</td>
                            <td class="numeric">R$ {{ number_format($session['opening'], 2, ',', '.') }}</td>
                            <td class="numeric">R$ {{ number_format($session['expected'], 2, ',', '.') }}</td>
                            <td class="numeric">R$ {{ number_format($session['informed'], 2, ',', '.') }}</td>
                            <td class="numeric">R$ {{ number_format($session['difference'], 2, ',', '.') }}</td>
                            <td><x-ui.badge :variant="$session['status'] === 'conferido' ? 'success' : 'warning'">{{ $session['status'] === 'conferido' ? 'Conferido' : 'Diferença registrada' }}</x-ui.badge></td>
                            <td><a href="{{ route('cash-register.closing', $session['id']) }}">Ver fechamento</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-slot:table>

            <x-slot:cards>
                <ul class="ui-mobile-records">
                    @foreach (array_reverse($closedSessions) as $session)
                        <li>
                            <div>
                                <strong>{{ $session['period'] }}</strong>
                                <small>{{ $session['operator'] }} · Esperado R$ {{ number_format($session['expected'], 2, ',', '.') }}</small>
                            </div>
                            <time>R$ {{ number_format($session['difference'], 2, ',', '.') }}</time>
                            <x-ui.badge :variant="$session['status'] === 'conferido' ? 'success' : 'warning'">{{ $session['status'] === 'conferido' ? 'Conferido' : 'Diferença' }}</x-ui.badge>
                            <a href="{{ route('cash-register.closing', $session['id']) }}">Ver fechamento</a>
                        </li>
                    @endforeach
                </ul>
            </x-slot:cards>
        </x-ui.responsive-table>
    </section>
</div>

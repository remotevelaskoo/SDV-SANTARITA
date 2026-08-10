<div class="validation-page">
    @if ($feedback)
        <section class="validation-feedback" aria-label="Resultado do atendimento">
            <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
                {{ $feedback['message'] }}
                <x-slot:action>
                    <x-ui.button variant="ghost" size="sm" wire:click="startNewValidation">
                        Iniciar nova validação
                    </x-ui.button>
                </x-slot:action>
            </x-ui.toast>

            @if ($protocol)
                <x-ui.protocol
                    :number="$protocol"
                    :status="$feedback['title']"
                    datetime="10/08/2026 às 14:32"
                    :tone="$feedback['variant'] === 'danger' ? 'danger' : ($feedback['variant'] === 'warning' ? 'warning' : 'success')"
                />
            @endif
        </section>
    @endif

    <section class="validation-context" aria-label="Contexto do atendimento">
        <div><span>Ponto de acesso</span><strong>Portaria Principal</strong></div>
        <div><span>Direção</span><strong>Entrada</strong></div>
        <div><span>Integração</span><x-ui.badge variant="success" icon="check-circle">Conectada</x-ui.badge></div>
        <div><span>Ambiente</span><x-ui.badge variant="info">Demonstração</x-ui.badge></div>
    </section>

    <section class="validation-section" aria-labelledby="validation-person-title">
        <header class="validation-section__header">
            <span class="validation-section__number" aria-hidden="true">1</span>
            <div>
                <h2 id="validation-person-title">Identificação da pessoa</h2>
                <p>Confira o cadastro, o vínculo e a autorização antes da decisão.</p>
            </div>
            <time datetime="2026-08-10T14:32:15-03:00">10/08/2026 · 14:32:15</time>
        </header>

        <div class="validation-person-grid">
            <x-ui.person-summary
                name="Marcos Vinicius da Silva"
                initials="MV"
                document="***.654.321-**"
                type="Morador"
                property="Bloco A · Apto 102"
                responsible="Próprio morador"
                status="Cadastro ativo"
                validity="Acesso permanente"
            >
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm" disabled title="Consulta completa será conectada ao cadastro em uma próxima etapa">
                        Ver cadastro completo
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.person-summary>

            <article class="validation-status-panel">
                <header>
                    <div><span>Status para decisão</span><strong>Condições verificadas</strong></div>
                    <x-ui.badge variant="success">Pronto</x-ui.badge>
                </header>
                <ul>
                    <li><x-icon name="check-circle" /><span><strong>Cadastro</strong><small>Ativo</small></span></li>
                    <li><x-icon name="check-circle" /><span><strong>Vínculo</strong><small>Morador vigente</small></span></li>
                    <li><x-icon name="check-circle" /><span><strong>Autorização</strong><small>Entrada permitida</small></span></li>
                    <li><x-icon name="check-circle" /><span><strong>Documento</strong><small>Validado</small></span></li>
                    <li><x-icon name="check-circle" /><span><strong>Face</strong><small>Sincronizada</small></span></li>
                </ul>
            </article>
        </div>

        <x-ui.alert variant="info" title="Dados demonstrativos">
            Esta pessoa e estes documentos são exemplos usados para validar o funcionamento e o visual da P06.
        </x-ui.alert>
    </section>

    <section class="validation-section" aria-labelledby="validation-vehicle-title">
        <header class="validation-section__header">
            <span class="validation-section__number" aria-hidden="true">2</span>
            <div>
                <h2 id="validation-vehicle-title">Veículo e leitura da placa</h2>
                <p>Compare a placa capturada com o veículo vinculado à pessoa.</p>
            </div>
        </header>

        <x-ui.lpr-comparison
            recognized="ABC1D23"
            registered="ABC1D23"
            :confidence="98"
            vehicle="Toyota Corolla · Prata · 2022"
            captured-at="10/08/2026 às 14:32:10"
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" size="sm" disabled title="Alteração de veículo será conectada ao cadastro em uma próxima etapa">
                    Alterar placa ou veículo
                </x-ui.button>
            </x-slot:actions>
        </x-ui.lpr-comparison>
    </section>

    <section class="validation-section" aria-labelledby="validation-contribution-title">
        <header class="validation-section__header">
            <span class="validation-section__number" aria-hidden="true">3</span>
            <div>
                <h2 id="validation-contribution-title">Contribuição / taxa de acesso</h2>
                <p>O pagamento é registrado separadamente e não autoriza a entrada por si só.</p>
            </div>
            <x-ui.badge variant="success">Caixa aberto</x-ui.badge>
        </header>

        <fieldset class="validation-contribution-options">
            <legend class="sr-only">Selecione a situação da contribuição</legend>
            <x-ui.radio id="contribution-yes" name="contribution" value="yes" label="Contribui" description="Entrada paga neste acesso" :checked="$contribution === 'yes'" wire:model.live="contribution" />
            <x-ui.radio id="contribution-no" name="contribution" value="no" label="Não contribui" description="Entrada sem pagamento" :checked="$contribution === 'no'" wire:model.live="contribution" />
            <x-ui.radio id="contribution-exempt" name="contribution" value="exempt" label="Isento" description="Pessoa isenta de contribuição" :checked="$contribution === 'exempt'" wire:model.live="contribution" />
        </fieldset>

        @if ($contribution === 'yes')
            <div class="validation-contribution-details">
                <div class="validation-contribution-fields">
                    <x-ui.field id="contribution-value" label="Valor da contribuição" value="R$ 15,00" readonly />
                    <x-ui.select id="payment-method" label="Forma de pagamento" wire:model.live="paymentMethod" :error="$errors->first('paymentMethod')">
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão</option>
                    </x-ui.select>
                    <x-ui.field id="received-from" label="Recebido de" value="Marcos Vinicius da Silva" readonly />
                </div>

                <aside class="validation-payment-summary" aria-label="Resumo do pagamento">
                    <span>Resumo do pagamento</span>
                    <dl>
                        <div><dt>Valor</dt><dd>R$ 15,00</dd></div>
                        <div><dt>Desconto</dt><dd>R$ 0,00</dd></div>
                        <div class="is-total"><dt>Total</dt><dd>R$ 15,00</dd></div>
                    </dl>
                    <small>Caixa: Portaria Principal · Tatiane</small>
                </aside>
            </div>
        @elseif ($contribution === 'no')
            <x-ui.alert variant="warning" title="Sem contribuição">
                A decisão foi registrada sem pagamento. Isso não altera as condições de autorização acima.
            </x-ui.alert>
        @else
            <x-ui.alert variant="info" title="Isenção selecionada">
                A isenção será registrada no atendimento e poderá ser consultada posteriormente.
            </x-ui.alert>
        @endif
    </section>

    <section class="validation-section" aria-labelledby="validation-notes-title">
        <header class="validation-section__header">
            <span class="validation-section__number" aria-hidden="true">4</span>
            <div>
                <h2 id="validation-notes-title">Observações</h2>
                <p>Registre somente informações úteis para este atendimento.</p>
            </div>
        </header>

        <div class="validation-notes-grid">
            <label class="validation-notes-field" for="validation-notes">
                <span>Informações adicionais</span>
                <textarea id="validation-notes" wire:model.live="notes" maxlength="200" rows="5" placeholder="Digite uma observação sobre este acesso…" @class(['is-invalid' => $errors->has('notes')])></textarea>
                <small><span>{{ mb_strlen($notes) }}</span>/200 caracteres</small>
                @error('notes') <strong role="alert">{{ $message }}</strong> @enderror
            </label>

            <aside class="validation-important-info">
                <strong>Informações importantes</strong>
                <ul>
                    <li>Confira os dados antes da decisão.</li>
                    <li>Alterações ficam registradas para auditoria.</li>
                    <li>Uma negativa exige motivo.</li>
                    <li>Esta tela ainda não aciona equipamento real.</li>
                </ul>
            </aside>
        </div>
    </section>

    <section class="validation-decision" aria-labelledby="validation-decision-title">
        <header>
            <div>
                <span>Decisão final</span>
                <h2 id="validation-decision-title">O que deseja fazer com este atendimento?</h2>
            </div>
            <x-ui.badge variant="info">Protótipo P06</x-ui.badge>
        </header>

        <div class="validation-decision__actions">
            <div class="validation-decision__modal">
                <x-ui.modal
                    id="deny-access-modal"
                    title="Negar entrada"
                    description="Informe o motivo para manter o registro auditável."
                    trigger-label="Negar entrada"
                    trigger-variant="danger"
                >
                    <div class="validation-denial-form">
                        <x-ui.select id="denial-reason" label="Motivo da negativa" wire:model="denialReason" :error="$errors->first('denialReason')" required>
                            <option value="sem_autorizacao">Sem autorização válida</option>
                            <option value="documento_invalido">Documento inválido</option>
                            <option value="vinculo_irregular">Vínculo irregular</option>
                            <option value="decisao_operador">Decisão justificada do operador</option>
                        </x-ui.select>

                        <label class="validation-notes-field" for="denial-details">
                            <span>Detalhes adicionais</span>
                            <textarea id="denial-details" wire:model="denialDetails" maxlength="200" rows="3" placeholder="Explique a negativa, se necessário…"></textarea>
                        </label>
                    </div>

                    <x-slot:confirm>
                        <form method="dialog">
                            <x-ui.button type="submit" variant="danger" wire:click="deny" wire:loading.attr="disabled" wire:target="deny">
                                Confirmar negativa
                            </x-ui.button>
                        </form>
                    </x-slot:confirm>
                </x-ui.modal>
                <small>Registra a negativa sem enviar comando.</small>
            </div>

            <div>
                <x-ui.button variant="warning" wire:click="savePending" wire:loading.attr="disabled" wire:target="savePending">
                    Salvar sem liberar
                </x-ui.button>
                <small>Guarda o atendimento para continuar depois.</small>
            </div>

            <div>
                <x-ui.button variant="success" wire:click="release" wire:loading.attr="disabled" wire:target="release">
                    Validar e liberar
                </x-ui.button>
                <small>Simula a autorização, sem acionar portão real.</small>
            </div>
        </div>

        <p><x-icon name="info" /> P06 em demonstração: nenhuma das ações desta página envia comandos para equipamentos físicos.</p>
    </section>
</div>

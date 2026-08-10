<div class="pre-registration-public">
    <aside class="pre-registration-brand-panel" aria-label="Condomínio Santa Rita">
        <a class="pre-registration-brand" href="{{ route('login') }}">
            <span aria-hidden="true">SDV</span>
            <strong>SDV Access</strong>
            <small>Condomínio Santa Rita</small>
        </a>
        <div>
            <x-icon name="shield" />
            <h1>Chegue com seus dados preparados</h1>
            <p>Informe seus dados antecipadamente para reduzir o tempo de atendimento na portaria.</p>
        </div>
        <ul>
            <li><x-icon name="check-circle" /> Processo guiado em seis etapas</li>
            <li><x-icon name="check-circle" /> Dados protegidos e revisáveis</li>
            <li><x-icon name="check-circle" /> Acompanhamento por protocolo</li>
        </ul>
    </aside>

    <main class="pre-registration-public__main">
        @if (! $started)
            <section class="pre-registration-welcome" aria-labelledby="pre-registration-welcome-title">
                <x-ui.badge variant="success">Convite válido</x-ui.badge>
                <span class="pre-registration-welcome__icon"><x-icon name="key" /></span>
                <h2 id="pre-registration-welcome-title">Bem-vindo ao Santa Rita</h2>
                <p>Seu acesso turístico à <strong>Praia do Santa Rita</strong> foi pré-convidado. Adiante seus dados para análise da portaria.</p>

                <dl>
                    <div><dt>Tipo</dt><dd>Turista</dd></div>
                    <div><dt>Período da visita</dt><dd>10/08/2026 até 17/08/2026</dd></div>
                    <div><dt>Convite válido até</dt><dd>10/08/2026 · 17:30</dd></div>
                </dl>

                <x-ui.alert variant="info" title="Antes de começar">
                    Solicitaremos identificação, documento e selfie para análise. O envio não garante entrada e não abre nenhum acesso físico.
                </x-ui.alert>

                <x-ui.button variant="primary" wire:click="start">Iniciar pré-cadastro</x-ui.button>
                <button class="pre-registration-text-link" type="button" disabled>Já enviou? Acompanhar situação</button>

                <small>Ao continuar, você poderá consultar o aviso de privacidade e escolher se deseja enviar os dados.</small>
            </section>
        @elseif ($submitted)
            <section class="pre-registration-result" aria-labelledby="pre-registration-result-title">
                <span class="pre-registration-result__icon"><x-icon name="check-circle" /></span>
                <x-ui.badge variant="warning">Aguardando análise</x-ui.badge>
                <h2 id="pre-registration-result-title">Pré-cadastro enviado</h2>
                <p>Seus dados foram encaminhados para análise da portaria.</p>

                <x-ui.protocol :number="$protocol" status="Aguardando análise" datetime="10/08/2026 às 18:12" tone="warning" />

                <x-ui.alert variant="warning" title="O protocolo não é uma autorização">
                    Mesmo após aprovação, sua entrada ainda será validada pela portaria conforme o período e as regras vigentes.
                </x-ui.alert>

                <div class="pre-registration-result__actions">
                    <x-ui.button variant="secondary" disabled>Acompanhar situação</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="restart">Finalizar demonstração</x-ui.button>
                </div>
            </section>
        @else
            <section class="pre-registration-flow" aria-labelledby="pre-registration-step-title">
                <header class="pre-registration-flow__header">
                    <div>
                        <a class="pre-registration-mobile-brand" href="{{ route('login') }}"><span aria-hidden="true">SDV</span> SDV Access</a>
                        <small>Etapa {{ $step }} de 6</small>
                        <h2 id="pre-registration-step-title">{{ $steps[$step - 1]['label'] }}</h2>
                        <p>{{ $steps[$step - 1]['description'] }}</p>
                    </div>
                    <x-ui.badge variant="success">Convite válido</x-ui.badge>
                </header>

                <div class="pre-registration-progress" role="progressbar" aria-label="Progresso do pré-cadastro" aria-valuemin="1" aria-valuemax="6" aria-valuenow="{{ $step }}">
                    <span style="width: {{ ($step / 6) * 100 }}%"></span>
                </div>

                <div class="pre-registration-flow__content">
                    <aside class="pre-registration-steps-desktop">
                        <x-ui.stepper :steps="$steps" :current="$step" />
                    </aside>

                    <div class="pre-registration-step-content">
                        @if ($draftSaved)
                            <x-ui.toast variant="success" title="Rascunho preservado" :dismissible="false">
                                Os dados desta demonstração foram mantidos para você continuar.
                            </x-ui.toast>
                        @endif

                        @if ($step === 1)
                            <div class="pre-registration-fields">
                                <x-ui.field id="pre-name" label="Nome completo" wire:model="name" :error="$errors->first('name')" required />
                                <x-ui.field id="pre-cpf" label="CPF" wire:model="cpf" placeholder="000.000.000-00" :error="$errors->first('cpf')" required />
                                <x-ui.field id="pre-birth-date" type="date" label="Data de nascimento" wire:model="birthDate" :error="$errors->first('birthDate')" required />
                                <x-ui.field id="pre-phone" type="tel" label="Telefone com DDD" wire:model="phone" placeholder="(12) 99999-9999" :error="$errors->first('phone')" required />
                                <x-ui.field id="pre-email" type="email" label="E-mail" wire:model="email" :error="$errors->first('email')" required />
                                <x-ui.select id="pre-access-type" label="Tipo de acesso" wire:model="accessType" :error="$errors->first('accessType')" required>
                                    <option value="visitante">Visitante</option>
                                    <option value="turista">Turista</option>
                                    <option value="prestador">Prestador</option>
                                </x-ui.select>
                            </div>
                        @elseif ($step === 2)
                            <x-ui.alert variant="info" title="Endereço informado">
                                Este é o seu endereço para esta solicitação. Ele não é o destino da visita e não altera o cadastro do imóvel.
                            </x-ui.alert>
                            <p class="pre-registration-zip-hint">
                                <span wire:loading wire:target="lookupZipCode" class="ui-loading"><span class="ui-spinner" aria-hidden="true"></span> Buscando endereço pelo CEP…</span>
                                <span wire:loading.remove wire:target="lookupZipCode">Ao sair do campo CEP, preenchemos o restante do endereço automaticamente.</span>
                            </p>
                            @if ($zipCodeLookupFailed)
                                <x-ui.alert variant="warning" title="CEP não encontrado">Não localizamos este CEP automaticamente. Preencha o endereço manualmente.</x-ui.alert>
                            @endif
                            <div class="pre-registration-fields pre-registration-fields--address">
                                <x-ui.field id="pre-zip-code" label="CEP" wire:model="zipCode" wire:blur="lookupZipCode" placeholder="00000-000" :error="$errors->first('zipCode')" required />
                                <x-ui.field id="pre-address" label="Endereço" wire:model="address" :error="$errors->first('address')" required />
                                <x-ui.field id="pre-address-number" label="Número" wire:model="addressNumber" :error="$errors->first('addressNumber')" required />
                                <x-ui.field id="pre-address-complement" label="Complemento" wire:model="addressComplement" />
                                <x-ui.field id="pre-district" label="Bairro" wire:model="district" :error="$errors->first('district')" required />
                                <x-ui.field id="pre-city" label="Cidade" wire:model="city" :error="$errors->first('city')" required />
                                <x-ui.field id="pre-state" label="Estado" wire:model="state" placeholder="SP" :error="$errors->first('state')" required />
                            </div>
                            @if ($accessType === 'turista')
                                <article class="pre-registration-destination"><span>Destino turístico</span><strong>Praia do Santa Rita</strong><small>Sem vínculo com imóvel — sujeito à autorização e validação da portaria</small></article>
                            @else
                                <article class="pre-registration-destination pre-registration-destination--property">
                                    <span>Destino da visita</span>
                                    <x-ui.select id="pre-destination-property" label="Imóvel de destino" wire:model.live="destinationProperty" :error="$errors->first('destinationProperty')" required>
                                        @foreach ($this->destinationOptions() as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </x-ui.select>
                                    <small>Responsável: {{ $this->destinationResponsible() }}</small>
                                </article>
                            @endif
                        @elseif ($step === 3)
                            <div class="pre-registration-capture">
                                <span class="pre-registration-capture__icon"><x-icon name="file" /></span>
                                <h3>Fotografe ou envie seu documento</h3>
                                <p>Mantenha o documento inteiro visível, sem reflexos e com o texto legível.</p>
                                @if ($documentReady)
                                    <x-ui.alert variant="success" title="Documento pronto para análise">
                                        Imagem demonstrativa recebida. O OCR apenas sugerirá dados e a portaria fará a conferência.
                                    </x-ui.alert>
                                    <x-ui.button variant="secondary" wire:click="$set('documentReady', false)">Substituir documento</x-ui.button>
                                @else
                                    <x-ui.button variant="primary" wire:click="markDocumentReady">Simular envio do documento</x-ui.button>
                                    @error('documentReady') <small class="pre-registration-error" role="alert">{{ $message }}</small> @enderror
                                @endif
                                <small>Protótipo: nenhum arquivo real é armazenado nesta etapa.</small>
                            </div>
                        @elseif ($step === 4)
                            <div class="pre-registration-capture pre-registration-capture--selfie">
                                <span class="pre-registration-capture__icon"><x-icon name="users" /></span>
                                <h3>Prepare uma selfie recente</h3>
                                <p>Posicione o rosto em local iluminado, sem acessórios que dificultem a conferência.</p>
                                @if ($selfieReady)
                                    <x-ui.alert variant="success" title="Selfie pronta para análise">
                                        A imagem foi marcada como adequada nesta demonstração. Ela não cria uma credencial biométrica.
                                    </x-ui.alert>
                                    <x-ui.button variant="secondary" wire:click="$set('selfieReady', false)">Repetir selfie</x-ui.button>
                                @else
                                    <x-ui.button variant="primary" wire:click="markSelfieReady">Simular captura da selfie</x-ui.button>
                                    @error('selfieReady') <small class="pre-registration-error" role="alert">{{ $message }}</small> @enderror
                                @endif
                                <small>Você também poderá enviar um arquivo quando a captura real for implementada.</small>
                            </div>
                        @elseif ($step === 5)
                            <x-ui.switch id="pre-has-vehicle" label="Vou chegar com um veículo" description="Informar o veículo é opcional e não garante acesso." wire:model.live="hasVehicle" :checked="$hasVehicle" />

                            @if ($hasVehicle)
                                <div class="pre-registration-fields pre-registration-vehicle-fields">
                                    <x-ui.field id="pre-plate" label="Placa" wire:model="plate" placeholder="ABC1D23" :error="$errors->first('plate')" required />
                                    <x-ui.field id="pre-vehicle-model" label="Marca e modelo" wire:model="vehicleModel" :error="$errors->first('vehicleModel')" required />
                                    <x-ui.field id="pre-vehicle-color" label="Cor" wire:model="vehicleColor" :error="$errors->first('vehicleColor')" required />
                                </div>
                                <x-ui.alert variant="warning" title="O veículo será conferido na chegada">
                                    Uma placa informada no pré-cadastro não libera o acesso automaticamente.
                                </x-ui.alert>
                            @else
                                <x-ui.empty-state title="Nenhum veículo informado" description="Você pode continuar sem veículo ou ativar a opção acima para adicioná-lo." />
                            @endif
                        @else
                            <div class="pre-registration-review">
                                <article><span>Dados pessoais</span><strong>{{ $name ?: 'Não informado' }}</strong><small>{{ ucfirst($accessType) }} · {{ $cpf ?: 'CPF não informado' }}</small><button type="button" wire:click="editStep(1)">Editar</button></article>
                                <article><span>Endereço informado</span><strong>{{ $address ?: 'Não informado' }}, {{ $addressNumber ?: 's/n' }}</strong><small>{{ $city ?: 'Cidade' }}/{{ strtoupper($state ?: 'UF') }}</small><button type="button" wire:click="editStep(2)">Editar</button></article>
                                <article><span>Documento e selfie</span><strong>{{ $documentReady && $selfieReady ? 'Prontos para análise' : 'Pendentes' }}</strong><small>OCR e qualidade exigem conferência humana</small><button type="button" wire:click="editStep(3)">Editar</button></article>
                                <article><span>Veículo</span><strong>{{ $hasVehicle ? ($plate ?: 'Placa pendente') : 'Sem veículo' }}</strong><small>{{ $hasVehicle ? ($vehicleModel ?: 'Modelo pendente') : 'Etapa opcional' }}</small><button type="button" wire:click="editStep(5)">Editar</button></article>
                            </div>

                            <x-ui.checkbox
                                id="pre-privacy"
                                label="Confirmo que revisei os dados e li o aviso de privacidade"
                                description="Entendo que o envio será analisado e não garante entrada no condomínio."
                                wire:model="privacyAccepted"
                                :checked="$privacyAccepted"
                            />
                            @error('privacyAccepted') <small class="pre-registration-error" role="alert">{{ $message }}</small> @enderror

                            <x-ui.alert variant="warning" title="Pré-cadastro não é liberação">
                                A aprovação apenas prepara a solicitação. A entrada continuará sujeita à Validação de Entrada na portaria.
                            </x-ui.alert>
                        @endif
                    </div>
                </div>

                <footer class="pre-registration-flow__actions">
                    <div>
                        @if ($step > 1)
                            <x-ui.button variant="secondary" wire:click="previousStep">Voltar</x-ui.button>
                        @endif
                        <x-ui.button variant="ghost" wire:click="saveDraft">Salvar rascunho</x-ui.button>
                    </div>
                    @if ($step < 6)
                        <x-ui.button variant="primary" wire:click="nextStep">Continuar</x-ui.button>
                    @else
                        <x-ui.button variant="success" wire:click="submit">Enviar pré-cadastro</x-ui.button>
                    @endif
                </footer>
            </section>
        @endif
    </main>
</div>

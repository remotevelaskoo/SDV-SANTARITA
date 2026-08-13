<div class="person-registration">
    @if ($feedback)
        <x-ui.alert :variant="$feedback['variant']" :title="$feedback['title']">
            {{ $feedback['message'] }}
        </x-ui.alert>
    @endif

    <x-ui.card title="Tipo de acesso" description="Define quais campos e regras se aplicam a este cadastro">
        <fieldset class="ui-access-types">
            <legend class="sr-only">Tipo de acesso</legend>
            <div>
                @foreach ([
                    ['value' => 'resident', 'label' => 'Morador', 'description' => 'Residente com vínculo ativo', 'icon' => 'building'],
                    ['value' => 'tenant', 'label' => 'Inquilino', 'description' => 'Ocupante com contrato vigente', 'icon' => 'key'],
                    ['value' => 'provider', 'label' => 'Prestador', 'description' => 'Serviço autorizado', 'icon' => 'wrench'],
                    ['value' => 'visitor', 'label' => 'Visitante', 'description' => 'Entrada vinculada a responsável', 'icon' => 'users'],
                    ['value' => 'tourist', 'label' => 'Turista', 'description' => 'Hospedagem com período definido', 'icon' => 'package'],
                ] as $type)
                    <label>
                        <input type="radio" name="access_type" value="{{ $type['value'] }}" wire:model.live="accessType">
                        <span class="ui-access-types__icon"><x-icon :name="$type['icon']" /></span>
                        <span><strong>{{ $type['label'] }}</strong><small>{{ $type['description'] }}</small></span>
                        <x-icon name="check-circle" class="ui-access-types__check" />
                    </label>
                @endforeach
            </div>
        </fieldset>
    </x-ui.card>

    <div class="registration-grid">
        <div class="registration-main">
            <x-ui.card>
                <x-ui.stepper :steps="collect($this->steps())->map(fn ($step, $i) => [...$step, 'state' => $i + 1 < $currentStep ? 'complete' : ($i + 1 === $currentStep ? 'current' : 'future')])->all()" :current="$currentStep" />
            </x-ui.card>

            @if ($currentStep === 1)
                <x-ui.card title="Dados pessoais" description="Identificação da pessoa">
                    <div class="registration-fields">
                        <x-ui.upload id="photo" label="Foto da pessoa" accept=".jpg,.jpeg,.png" limit="Até 5 MB" wire:model="photo" />
                        @error('photo') <small class="ui-field__message ui-field__message--error">{{ $message }}</small> @enderror
                        <x-ui.field id="fullName" label="Nome completo" wire:model="fullName" required :error="$errors->first('fullName')" />
                        <x-ui.field id="socialName" label="Nome social" wire:model="socialName" />
                        <x-ui.field id="document" label="CPF" wire:model="document" wire:blur="checkDocument" required :error="$errors->first('document')" help="Use apenas números ou o formato 000.000.000-00" />
                        <x-ui.field id="rg" label="RG ou documento" wire:model="rg" />
                        <x-ui.field id="rgIssuer" label="Órgão emissor" wire:model="rgIssuer" />
                        <x-ui.field id="birthDate" label="Data de nascimento" type="date" wire:model="birthDate" required :error="$errors->first('birthDate')" />
                        <x-ui.select id="maritalStatus" label="Estado civil" wire:model="maritalStatus">
                            <option value="">Não informado</option>
                            <option value="solteiro">Solteiro(a)</option>
                            <option value="casado">Casado(a)</option>
                            <option value="uniao_estavel">União estável</option>
                            <option value="divorciado">Divorciado(a)</option>
                            <option value="viuvo">Viúvo(a)</option>
                        </x-ui.select>
                        <x-ui.field id="nationality" label="Nacionalidade" wire:model="nationality" />
                        <x-ui.field id="profession" label="Profissão" wire:model="profession" />
                        @if ($accessType === 'provider')
                            <x-ui.field id="company" label="Empresa" wire:model="company" required :error="$errors->first('company')" />
                        @endif
                        <x-ui.field id="email" label="E-mail" type="email" wire:model="email" :error="$errors->first('email')" />
                        <x-ui.field id="phone" label="Telefone principal" wire:model="phone" required :error="$errors->first('phone')" />
                    </div>

                    @if ($duplicateFound)
                        <x-ui.alert variant="warning" title="Pessoa já cadastrada" class="registration-duplicate-alert">
                            Já existe uma pessoa com este documento. Selecione o cadastro existente para criar um vínculo.
                        </x-ui.alert>
                    @endif
                </x-ui.card>
            @elseif ($currentStep === 2)
                <x-ui.card title="Documentos e fotos" description="Comprovação documental e foto facial">
                    <div class="registration-fields">
                        <x-ui.select id="documentType" label="Tipo documental" wire:model="documentType">
                            <option value="rg">RG</option>
                            <option value="cnh">CNH</option>
                            <option value="passaporte">Passaporte</option>
                            <option value="outro">Outro</option>
                        </x-ui.select>
                        <div class="registration-document-state">
                            <x-ui.badge :variant="match ($documentState) {
                                'enviado' => 'info',
                                'validado' => 'success',
                                'rejeitado' => 'danger',
                                default => 'neutral',
                            }">
                                {{ match ($documentState) {
                                    'enviado' => 'Enviado',
                                    'validado' => 'Validado',
                                    'rejeitado' => 'Rejeitado',
                                    default => 'Não enviado',
                                } }}
                            </x-ui.badge>
                            @if ($documentState === 'nao_enviado')
                                <x-ui.button variant="ghost" size="sm" wire:click="$set('documentState', 'enviado')">Simular envio</x-ui.button>
                            @endif
                        </div>
                    </div>
                    <div class="registration-uploads">
                        <x-ui.upload id="documentFront" label="Frente do documento" />
                        <x-ui.upload id="documentBack" label="Verso do documento" />
                        <x-ui.upload id="facialPhoto" label="Foto facial" accept=".jpg,.jpeg,.png" limit="Até 5 MB" source="Captura ou upload" />
                    </div>
                    <p class="registration-hint">OCR opcional: o resultado sugerido exige conferência humana e não substitui a validação do documento.</p>
                </x-ui.card>
            @elseif ($currentStep === 3)
                <x-ui.card title="Endereço e contato" description="Vínculo com o endereço do imóvel">
                    <x-ui.alert variant="info" title="Endereço compartilhado">
                        Este endereço pertence ao imóvel e é compartilhado pelos vínculos residenciais.
                    </x-ui.alert>
                    <div class="registration-fields">
                        <x-ui.field id="linkedPropertyLabel" label="Imóvel" :value="$linkedProperty['property']" readonly />
                        <x-ui.field id="linkedPropertyAddress" label="Endereço principal do imóvel" :value="$linkedProperty['address']" readonly />
                    </div>
                    <p class="registration-hint">Alterações estruturais no endereço só podem ser feitas a partir do cadastro do imóvel, por quem tiver permissão.</p>
                </x-ui.card>
            @elseif ($currentStep === 4)
                <x-ui.card title="Informações de acesso" description="Vigência, natureza e permissões do vínculo">
                    <div class="registration-fields">
                        <x-ui.select id="property" label="Imóvel" wire:model="property" required :error="$errors->first('property')">
                            <option value="">Selecione o imóvel</option>
                            @foreach ($imoveis as $imovel)
                                <option value="{{ $imovel->codigo }}">{{ $imovel->label() }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.select id="nature" label="Natureza" wire:model="nature" required :error="$errors->first('nature')">
                            <option value="proprietario">Proprietário</option>
                            <option value="morador">Morador</option>
                            <option value="inquilino">Inquilino</option>
                            <option value="outro">Outro ocupante</option>
                        </x-ui.select>
                        <x-ui.select id="role" label="Papel" wire:model="role" required :error="$errors->first('role')">
                            <option value="titular">Titular</option>
                            <option value="conjuge">Cônjuge</option>
                            <option value="filho">Filho(a)</option>
                            <option value="dependente">Dependente</option>
                            <option value="outro">Outro</option>
                        </x-ui.select>
                        @if (in_array($accessType, ['visitor', 'tourist'], true))
                            <x-ui.field id="responsible" label="Responsável" wire:model="responsible" required :error="$errors->first('responsible')" help="Pessoa que responde por este acesso" />
                        @endif
                        <x-ui.field id="startDate" label="Data de início" type="date" wire:model="startDate" required :error="$errors->first('startDate')" />
                        @unless ($indefiniteTerm)
                            <x-ui.field id="endDate" label="Data de término" type="date" wire:model="endDate" required :error="$errors->first('endDate')" />
                        @endunless
                        <x-ui.select id="schedule" label="Horário" wire:model="schedule">
                            <option value="integral">Integral</option>
                            <option value="comercial">Horário comercial</option>
                            <option value="personalizado">Personalizado</option>
                        </x-ui.select>
                    </div>

                    <x-ui.switch id="indefiniteTerm" label="Prazo indeterminado" description="Desmarque para exigir data de término" wire:model.live="indefiniteTerm" class="registration-term-switch" />

                    <fieldset class="registration-areas">
                        <legend>Áreas liberadas</legend>
                        <x-ui.checkbox id="area-comum" label="Áreas comuns" value="comum" wire:model="areas" />
                        <x-ui.checkbox id="area-garagem" label="Garagem" value="garagem" wire:model="areas" />
                        <x-ui.checkbox id="area-lazer" label="Área de lazer" value="lazer" wire:model="areas" />
                        <x-ui.checkbox id="area-servico" label="Portão de serviço" value="servico" wire:model="areas" />
                    </fieldset>
                </x-ui.card>
            @elseif ($currentStep === 5)
                <x-ui.card title="Observações" description="Informação operacional controlada, sem substituir campos estruturados">
                    <label class="ui-field">
                        <span class="ui-field__label">Observações</span>
                        <textarea class="ui-field__control" rows="5" maxlength="500" wire:model="notes" placeholder="Registre informações relevantes para a portaria ou administração"></textarea>
                        <small class="ui-field__message">{{ strlen($notes) }}/500 caracteres</small>
                    </label>
                </x-ui.card>
            @endif

            <x-ui.card>
                <div class="registration-step-nav">
                    <x-ui.button variant="secondary" wire:click="previousStep" :disabled="$currentStep === 1">Voltar</x-ui.button>

                    <x-ui.action-group>
                        @if ($currentStep < 5)
                            <x-ui.button wire:click="nextStep">Avançar</x-ui.button>
                        @endif
                    </x-ui.action-group>
                </div>
            </x-ui.card>

            <x-ui.card variant="status">
                <x-ui.action-group>
                    <x-ui.button variant="ghost" wire:click="cancel">Cancelar</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="saveDraft">Salvar rascunho</x-ui.button>
                    <x-ui.button variant="success" wire:click="activate">
                        <x-slot:icon><x-icon name="check-circle" /></x-slot:icon>
                        Salvar e ativar cadastro
                    </x-ui.button>
                </x-ui.action-group>
            </x-ui.card>
        </div>

        <aside class="registration-context">
            <x-ui.person-summary
                :name="$fullName !== '' ? $fullName : 'Nova pessoa'"
                :initials="$fullName !== '' ? collect(explode(' ', $fullName))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('') : '—'"
                :document="$document !== '' ? $document : 'Não informado'"
                :type="match ($accessType) { 'resident' => 'Morador', 'tenant' => 'Inquilino', 'provider' => 'Prestador', 'visitor' => 'Visitante', default => 'Turista' }"
                :property="$linkedProperty['property']"
                status="Rascunho"
                tone="warning"
            />

            <x-ui.link-panel
                :property="$linkedProperty['property']"
                :nature="match ($nature) { 'proprietario' => 'Proprietário', 'morador' => 'Morador', 'inquilino' => 'Inquilino', default => 'Outro ocupante' }"
                :responsibility="$role === 'titular' ? 'Responsável principal' : 'Sem responsabilidade administrativa'"
                :period="$indefiniteTerm ? 'Prazo indeterminado' : ($endDate !== '' ? 'Até '.\Illuminate\Support\Carbon::parse($endDate)->format('d/m/Y') : 'A definir')"
                status="Vínculo em elaboração"
                tone="warning"
            />

            @if (count($areas))
                <x-ui.empty-state title="Nenhum veículo vinculado" description="Veículos podem ser adicionados após ativar o cadastro." icon="car" />
            @endif

            <x-ui.sync-status
                status="Sincronização facial"
                equipment="Controladora Principal"
                last-attempt="Ainda não enviado"
                tone="warning"
                description="Será enfileirada somente após o cadastro ser ativado."
            />

            <x-ui.card title="Histórico" description="Nenhum evento registrado para este cadastro ainda">
                <x-ui.empty-state title="Sem histórico" description="Eventos de vínculo, autorização e sincronização aparecerão aqui após a ativação." icon="scroll" />
            </x-ui.card>
        </aside>
    </div>
</div>

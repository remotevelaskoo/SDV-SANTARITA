@php
    $passos = $this->passosDoAtendimento();
    $ordemPassos = array_keys($passos);
    $indicePasso = array_search($passo, $ordemPassos, true);
    $ultimoPasso = $passo === 'concluir';
@endphp
<div class="validation-page sdv-one-validation">
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
                    :datetime="now()->format('d/m/Y \\à\\s H:i')"
                    :tone="$feedback['variant'] === 'danger' ? 'danger' : ($feedback['variant'] === 'warning' ? 'warning' : 'success')"
                />
            @endif

            @if ($envioFacialLiberacao)
                {{-- Turista liberado: 24 h enviadas ao terminal facial (não à cancela). --}}
                <x-ui.alert :variant="$envioFacialLiberacao['variant']" :title="$envioFacialLiberacao['title']">{{ $envioFacialLiberacao['message'] }}</x-ui.alert>
            @endif
        </section>
    @endif

    <section class="validation-context" aria-label="Contexto do atendimento">
        <div><span>Ponto de acesso</span><strong>Portaria Principal</strong></div>
        <div><span>Direção</span><strong>Entrada</strong></div>
        <div><span>Equipamentos</span><x-ui.badge variant="neutral" icon="info">Sem acionamento nesta tela</x-ui.badge></div>
        <div><span>Ambiente</span><x-ui.badge variant="info">Demonstração</x-ui.badge></div>
    </section>

    {{-- Barra de etapas do atendimento guiado (FluxoGuiadoAtendimento). --}}
    <nav class="atendimento-passos" aria-label="Etapas do atendimento">
        <ol>
            {{-- As cinco etapas sempre aparecem; a que não se aplica (foto já existente, pagamento de quem não é turista) fica apagada e é pulada. --}}
            @foreach (\App\Livewire\AccessValidation::PASSOS as $chave => $rotulo)
                @php
                    $posicao = array_search($chave, $ordemPassos, true);
                    $estado = match (true) {
                        $posicao === false => 'pulado',
                        $chave === $passo => 'atual',
                        $posicao < $indicePasso => 'feito',
                        default => 'proximo',
                    };
                @endphp
                <li @class(['atendimento-passos__item', "is-{$estado}"]) wire:key="passo-barra-{{ $chave }}">
                    @if ($estado === 'feito' && ! $protocol)
                        <button type="button" wire:click="irParaPasso('{{ $chave }}')" aria-label="Voltar para {{ $rotulo }}">
                            <span class="atendimento-passos__numero"><x-icon name="check-circle" /></span><span>{{ $rotulo }}</span>
                        </button>
                    @else
                        <span @if ($estado === 'atual') aria-current="step" @endif @if ($estado === 'pulado') title="Não se aplica a este atendimento" @endif>
                            <span class="atendimento-passos__numero">{{ $loop->iteration }}</span><span>{{ $rotulo }}{{ $estado === 'pulado' ? ' (não se aplica)' : '' }}</span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="atendimento-passo" data-rolar="atendimento-{{ $passo }}" wire:key="atendimento-passo-{{ $passo }}">
    @if ($passo === 'dados')
    {{-- ===================== 1. DADOS ===================== --}}
    <section class="validation-section" aria-labelledby="validation-person-title">
        <header class="validation-section__header">
            <div>
                <h2 id="validation-person-title">Quem está sendo atendido?</h2>
                <p>Busque a pessoa. Se não tiver cadastro, fotografe o documento ou digite os dados.</p>
            </div>
            <div class="validation-person-actions">
                <time datetime="{{ now()->toAtomString() }}">{{ now()->format('d/m/Y · H:i') }}</time>
                @if (! $quickRegistrationOpen && ! $currentPerson['selected'] && auth()->user()?->hasPermission('pessoas.gerenciar'))
                    <x-ui.button variant="warning" size="sm" wire:click="openQuickRegistration">
                        Pessoa não encontrada? Cadastro rápido
                    </x-ui.button>
                @endif
            </div>
        </header>

        @if ($avisoPreCadastro)
            <x-ui.alert :variant="$avisoPreCadastro['variant']" :title="$avisoPreCadastro['title']">
                {{ $avisoPreCadastro['message'] }}
            </x-ui.alert>
        @endif

        @if ($quickRegistrationOpen)
            @php
                $existente = $this->cadastroExistenteResumo();
                $conferirBadge = fn (string $campo) => in_array($campo, $camposConferencia, true);
            @endphp
            <section class="quick-registration" aria-labelledby="quick-registration-title">
                <header>
                    <div>
                        <span><x-icon name="users" /></span>
                        <div><h3 id="quick-registration-title">Cadastro rápido</h3><p>Fotografe o documento para preencher sozinho, ou digite os dados.</p></div>
                    </div>
                    <x-ui.badge variant="warning">Cadastro provisório</x-ui.badge>
                </header>

                @if ($quickEtapa === 'documento' && $this->podeCapturarDocumento())
                    <div class="quick-registration__etapa" data-rolar="cadastro-rapido-documento">
                        @include('livewire.partials.captura-documento')
                    </div>
                    <footer class="quick-registration__acoes">
                        <x-ui.button type="button" variant="secondary" wire:click="cancelQuickRegistration">Cancelar cadastro</x-ui.button>
                    </footer>
                @else
                <form wire:submit="continuar" data-rolar="cadastro-rapido-{{ $quickEtapa }}">
                    @if (($analiseDocumento['status'] ?? null) === 'sugestao')
                        <x-ui.alert variant="info" title="Dados sugeridos pela leitura">
                            Confira os campos preenchidos. Os marcados com "Requer conferência" precisam da sua confirmação. A leitura não aprova cadastro nem libera acesso.
                            @if (! empty($analiseDocumento['extras']['validade']['valor']))<br>Validade do documento: {{ $analiseDocumento['extras']['validade']['valor'] }}@endif
                        </x-ui.alert>
                        @if ($analiseDocumento['duplicidade'])
                            <x-ui.alert variant="danger" title="Possível duplicidade">Já existe pessoa com este CPF. Use o cadastro existente em vez de criar outro.</x-ui.alert>
                        @endif
                    @endif
                    @if ($avisoLeitura)
                        <x-ui.alert variant="warning" title="Suas correções foram mantidas">{{ $avisoLeitura }}</x-ui.alert>
                    @endif

                    <div class="quick-registration__fields">
                        <div class="nome-por-voz" x-data="nomePorVoz()" x-on:input="digitou()" x-on:cadastro-rapido-encerrado.window="cancelar()" x-on:cadastro-rapido-foco-nome.window="focarNome()">
                            <x-ui.field id="quick-name" label="Nome completo" wire:model.blur="quickName" :error="$errors->first('quickName')" required />
                            {{-- Voz no nome (docs/021 §12) e no CPF: começa no clique, não salva nem envia nada. --}}
                            <div class="nome-por-voz__acoes">
                                <template x-if="suportado">
                                    <span class="nome-por-voz__botoes">
                                        <button type="button" class="ui-button ui-button--secondary ui-button--sm nome-por-voz__microfone" x-on:click="alternar()" :aria-pressed="ouvindo.toString()" :aria-label="ouvindo ? 'Cancelar' : 'Preencher por voz'" :title="ouvindo ? 'Cancelar o preenchimento por voz' : 'Preencher por voz'">
                                            <span class="ui-button__content">
                                                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><path d="M12 17v5"/></svg>
                                                <span x-text="ouvindo ? 'Cancelar' : 'Preencher por voz'"></span>
                                            </span>
                                        </button>
                                    </span>
                                </template>
                                <small class="nome-por-voz__mensagem" role="status" aria-live="polite" x-text="suportado ? mensagem : textoSemSuporte"></small>
                            </div>
                            @if ($conferirBadge('quickName'))<span class="captura-documento__conferir"><x-ui.badge variant="warning">Requer conferência</x-ui.badge> <button type="button" class="ui-button ui-button--ghost ui-button--sm" wire:click="confirmarCampo('quickName')"><span class="ui-button__content">Confirmar</span></button></span>@endif
                        </div>
                        <div class="nome-por-voz" x-data="nomePorVoz({ tipo: 'cpf', metodo: 'preencherCpfPorVoz' })" x-on:input="digitou()" x-on:cadastro-rapido-encerrado.window="cancelar()">
                            <x-ui.field id="quick-document" label="CPF" wire:model.blur="quickDocument" wire:blur="checkQuickDocument" help="A verificação evita criar uma pessoa duplicada." :error="$errors->first('quickDocument')" required />
                            @include('livewire.partials.voz-botao', ['rotulo' => 'Ditar CPF'])
                            @if ($conferirBadge('quickDocument'))<span class="captura-documento__conferir"><x-ui.badge variant="warning">Requer conferência</x-ui.badge> <button type="button" class="ui-button ui-button--ghost ui-button--sm" wire:click="confirmarCampo('quickDocument')"><span class="ui-button__content">Confirmar</span></button></span>@endif
                        </div>
                        <div>
                            <x-ui.field id="quick-birth" label="Data de nascimento" type="date" wire:model.blur="quickBirthDate" :error="$errors->first('quickBirthDate')" />
                            @if ($conferirBadge('quickBirthDate'))<span class="captura-documento__conferir"><x-ui.badge variant="warning">Requer conferência</x-ui.badge> <button type="button" class="ui-button ui-button--ghost ui-button--sm" wire:click="confirmarCampo('quickBirthDate')"><span class="ui-button__content">Confirmar</span></button></span>@endif
                        </div>
                        <div>
                            <x-ui.field id="quick-doc-numero" :label="$capturaTipoDocumento === 'rg_cin' ? 'Número do RG/CIN' : 'Número da CNH'" wire:model.blur="quickDocNumero" help="Guardado como documento próprio; não substitui o CPF." :error="$errors->first('quickDocNumero')" />
                            @if ($conferirBadge('quickDocNumero'))<span class="captura-documento__conferir"><x-ui.badge variant="warning">Requer conferência</x-ui.badge> <button type="button" class="ui-button ui-button--ghost ui-button--sm" wire:click="confirmarCampo('quickDocNumero')"><span class="ui-button__content">Confirmar</span></button></span>@endif
                        </div>
                        <x-ui.field id="quick-phone" label="Telefone (opcional)" wire:model="quickPhone" placeholder="(35) 99999-0000" help="Se informado, use DDD e número." :error="$errors->first('quickPhone')" />
                        <x-ui.select id="quick-access-type" label="Tipo de acesso" wire:model.live="quickAccessType" :error="$errors->first('quickAccessType')" required>
                            <option value="visitante">Visitante</option>
                            <option value="prestador">Prestador</option>
                            <option value="morador">Morador</option>
                            <option value="turista">Turista</option>
                            <option value="outro">Outro</option>
                        </x-ui.select>
                        @if ($quickAccessType === 'visitante')
                            {{-- Visitante: responsável obrigatório (docs/021 §14). --}}
                            <x-ui.field id="quick-responsible" label="Responsável pelo visitante" wire:model="quickResponsible" help="Quem recebe o visitante no imóvel." :error="$errors->first('quickResponsible')" required />
                        @elseif ($quickAccessType === 'morador')
                            <x-ui.field id="quick-responsible" label="Responsável (opcional)" wire:model="quickResponsible" help="Deixe em branco se não houver." :error="$errors->first('quickResponsible')" />
                        @endif
                        <x-ui.field id="quick-property" :label="$quickAccessType === 'visitante' ? 'Código do imóvel' : 'Código do imóvel (opcional)'" wire:model="quickPropertyCode" placeholder="SRA-A-102" :help="match ($quickAccessType) { 'visitante' => 'Imóvel que o visitante vai visitar.', 'turista' => 'Turista não ganha vínculo com imóvel.', default => 'Sem imóvel, nenhum vínculo é criado e o envio à facial depende de uma autorização vigente.' }" :error="$errors->first('quickPropertyCode')" :required="$quickAccessType === 'visitante'" />
                    </div>

                    @if ($quickDuplicateFound)
                        <x-ui.alert variant="danger" title="Pessoa já cadastrada">
                            Não criaremos outro registro com este documento.
                            @if ($existente)
                                Cadastro encontrado: <strong>{{ $existente['nome'] }}</strong> ({{ $existente['documento'] }}).
                            @endif
                            <x-slot:action>
                                <x-ui.button type="button" variant="secondary" size="sm" wire:click="usarCadastroExistente">Usar este cadastro</x-ui.button>
                            </x-slot:action>
                        </x-ui.alert>
                    @elseif ($quickPessoaExistenteId)
                        <x-ui.alert variant="info" title="Usando o cadastro existente">
                            Identidade, contato e foto atuais não serão sobrescritos. O atendimento será vinculado a esta pessoa.
                        </x-ui.alert>
                    @endif

                    <label class="quick-registration__notes" for="quick-notes">
                        <span>Observação do cadastro rápido</span>
                        <textarea id="quick-notes" wire:model="quickNotes" maxlength="200" rows="2" placeholder="Exemplo: documento será complementado pela administração…"></textarea>
                        <small>{{ mb_strlen($quickNotes) }}/200 caracteres</small>
                    </label>

                    <footer class="quick-registration__acoes">
                        <x-ui.button type="button" variant="secondary" wire:click="cancelQuickRegistration">Cancelar cadastro</x-ui.button>
                        @if ($this->podeCapturarDocumento())
                            <x-ui.button type="button" variant="ghost" wire:click="irParaEtapa('documento')">Fotografar documento de novo</x-ui.button>
                        @endif
                    </footer>
                </form>
                @endif
            </section>
        @endif

        @unless ($currentPerson['selected'] || $quickRegistrationOpen)
            @php($busca = $this->resultadosBusca)
            <div class="validation-search" role="search">
                <label for="validation-search-input">
                    <span>Buscar pessoa</span>
                    <span class="validation-search__input">
                        <x-icon name="search" />
                        <input
                            id="validation-search-input"
                            type="search"
                            wire:model.live.debounce.400ms="busca"
                            maxlength="80"
                            autocomplete="off"
                            placeholder="Nome, CPF ou documento, telefone, protocolo ou código do imóvel"
                            aria-describedby="validation-search-help"
                        >
                    </span>
                </label>
                <small id="validation-search-help">A busca considera somente esta implantação. Documento e telefone precisam ser digitados completos.</small>
                @error('busca')<small class="ui-field__message ui-field__message--error" role="alert">{{ $message }}</small>@enderror

                <div class="validation-search__state" wire:loading.flex wire:target="busca" aria-live="polite">
                    <x-icon name="refresh" /> Buscando…
                </div>

                <div wire:loading.remove wire:target="busca">
                    @if (mb_strlen(trim($this->busca)) < \App\Services\BuscaPessoasValidacao::TAMANHO_MINIMO)
                        <p class="validation-search__hint">Digite ao menos {{ \App\Services\BuscaPessoasValidacao::TAMANHO_MINIMO }} caracteres. Nenhuma pessoa é selecionada automaticamente.</p>
                    @elseif ($busca['resultados'] === [])
                        <x-ui.empty-state title="Nenhuma pessoa localizada" description="Confira o termo digitado ou faça o cadastro rápido sem perder este atendimento." icon="search">
                            <x-slot:action>
                                <x-ui.button variant="warning" size="sm" wire:click="openQuickRegistration">Cadastro rápido</x-ui.button>
                            </x-slot:action>
                        </x-ui.empty-state>
                    @else
                        <p class="validation-search__hint" aria-live="polite">
                            {{ count($busca['resultados']) }} {{ count($busca['resultados']) === 1 ? 'pessoa encontrada' : 'pessoas encontradas' }}{{ $busca['mais'] ? ' (há mais: refine a busca)' : '' }}. Selecione quem está sendo atendido.
                        </p>
                        <ul class="validation-search__results" aria-label="Pessoas encontradas">
                            @foreach ($busca['resultados'] as $resultado)
                                <li wire:key="busca-{{ $resultado['id'] }}">
                                    @if ($resultado['foto_url'])
                                        <img class="validation-search__photo" src="{{ $resultado['foto_url'] }}" alt="Foto de {{ $resultado['nome'] }}" loading="lazy" referrerpolicy="no-referrer">
                                    @else
                                        <span class="validation-search__photo is-empty" title="{{ $resultado['foto_estado'] === 'sem_permissao' ? 'Foto protegida pelo seu perfil' : 'Sem foto cadastrada' }}">{{ $resultado['iniciais'] }}</span>
                                    @endif
                                    <div class="validation-search__data">
                                        <strong>{{ $resultado['nome'] }}</strong>
                                        <small>{{ $resultado['documento'] }} · {{ $resultado['tipo'] }} · {{ $resultado['destino'] }}{{ $resultado['vinculos_extras'] > 0 ? ' (+'.$resultado['vinculos_extras'].')' : '' }}</small>
                                        <small>Vigência: {{ $resultado['vigencia'] }} · Encontrado por {{ implode(', ', $resultado['motivos']) }}</small>
                                    </div>
                                    <x-ui.badge :variant="$resultado['situacao_tom']">{{ $resultado['situacao'] }}</x-ui.badge>
                                    <x-ui.button variant="secondary" size="sm" wire:click="selecionarPessoa('{{ $resultado['id'] }}')" wire:loading.attr="disabled">Selecionar</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                        <p class="validation-search__hint">Não é quem procura? <button type="button" class="sensitive-reveal" wire:click="openQuickRegistration">Fazer cadastro rápido</button></p>
                    @endif
                </div>
            </div>
        @endunless

        @if ($currentPerson['selected'])
        <div class="validation-person-grid">
            <x-ui.person-summary
                :name="$currentPerson['name']"
                :initials="$currentPerson['initials']"
                :document="$currentPerson['document']"
                :type="$currentPerson['type']"
                :property="$currentPerson['property']"
                :responsible="$currentPerson['responsible']"
                :status="$currentPerson['status']"
                :validity="$currentPerson['validity']"
                :tone="$currentPerson['tone']"
                :photo-url="$currentPerson['photo_url']"
                :photo-state="$currentPerson['photo_state']"
                :photo-origin="$currentPerson['photo_origin']"
            >
                @if (count($currentPerson['links']) > 1 || $currentPerson['vehicles'] !== [])
                    <dl class="validation-person-extra">
                        @foreach (array_slice($currentPerson['links'], 1) as $link)
                            <div><dt>Outro vínculo</dt><dd>{{ $link['type'] }} · {{ $link['property'] }} · {{ $link['validity'] }}</dd></div>
                        @endforeach
                        @foreach ($currentPerson['vehicles'] as $vehicle)
                            <div><dt>Veículo</dt><dd>{{ $vehicle['plate'] }} · {{ $vehicle['description'] }}</dd></div>
                        @endforeach
                    </dl>
                @endif
                <x-slot:actions>
                    @if (auth()->user()?->hasPermission('dados-sensiveis.revelar'))
                        <x-ui.button variant="ghost" size="sm" wire:click="toggleDocumentReveal">
                            {{ $documentRevealed ? 'Ocultar documento' : 'Revelar documento' }}
                        </x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" size="sm" wire:click="trocarPessoa">
                        Trocar pessoa
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.person-summary>

            <article class="validation-status-panel">
                <header>
                    <div><span>Status para decisão</span><strong>{{ $currentPerson['tone'] === 'success' ? 'Conferir e decidir' : 'Revisão obrigatória' }}</strong></div>
                    <x-ui.badge :variant="$currentPerson['tone'] === 'success' ? 'info' : 'warning'">{{ $currentPerson['tone'] === 'success' ? 'Sem pendências de cadastro' : 'Pendente' }}</x-ui.badge>
                </header>
                <ul>
                    @foreach ($currentPerson['checks'] as $check)
                        <li @class(['is-pending' => $check['ok'] !== true])><x-icon :name="$check['ok'] === true ? 'check-circle' : ($check['ok'] === false ? 'alert' : 'clock')" /><span><strong>{{ $check['label'] }}</strong><small>{{ $check['value'] }}</small></span></li>
                    @endforeach
                </ul>
            </article>
        </div>

        @php($dadosIdent = $this->dadosIdentificacao)
        @if ($dadosIdent)
            <section class="validation-person-data" aria-labelledby="identificacao-dados-titulo" wire:key="dados-identificacao-{{ $currentPessoaId }}">
                <header>
                    <div>
                        <h3 id="identificacao-dados-titulo">Dados para conferência</h3>
                        <p>Compare com o documento apresentado. Se algo estiver errado, corrija antes de decidir.</p>
                    </div>
                    @if (! $editandoIdentificacao && $this->podeEditarIdentificacao())
                        <x-ui.button variant="secondary" size="sm" wire:click="editarIdentificacao">Editar dados</x-ui.button>
                    @endif
                </header>

                @if ($dadosIdentificacaoSalvos)
                    <x-ui.alert variant="success" title="Dados corrigidos">O cadastro da pessoa foi atualizado. Os valores anteriores ficam no histórico.</x-ui.alert>
                @endif

                @if ($editandoIdentificacao)
                    <div class="registration-fields">
                        <x-ui.field id="ident-nome" label="Nome completo" wire:model="identNome" :error="$errors->first('identNome')" required />
                        <x-ui.field id="ident-nome-social" label="Nome social (opcional)" wire:model="identNomeSocial" :error="$errors->first('identNomeSocial')" />
                        <x-ui.field id="ident-nascimento" type="date" label="Data de nascimento" wire:model="identNascimento" :error="$errors->first('identNascimento')" />
                        <div class="nome-por-voz" x-data="nomePorVoz({ tipo: 'cpf', metodo: 'preencherCpfIdentificacaoPorVoz' })" x-on:input="digitou()">
                            <x-ui.field id="ident-cpf" label="CPF" wire:model="identCpf" inputmode="numeric" :error="$errors->first('identCpf')" help="Corrija se estiver diferente do documento." />
                            @include('livewire.partials.voz-botao', ['rotulo' => 'Ditar CPF'])
                        </div>
                        <x-ui.field id="ident-telefone" type="tel" label="Telefone" wire:model="identTelefone" :error="$errors->first('identTelefone')" help="Com DDD. Deixe em branco se não houver." />
                        <x-ui.field id="ident-email" type="email" label="E-mail" wire:model="identEmail" :error="$errors->first('identEmail')" />
                    </div>
                    <div class="equipamento-management-acoes">
                        <x-ui.button variant="ghost" size="sm" wire:click="cancelarEdicaoIdentificacao">Cancelar</x-ui.button>
                        <x-ui.button variant="success" size="sm" wire:click="salvarIdentificacao" wire:loading.attr="disabled" wire:target="salvarIdentificacao">Salvar correção</x-ui.button>
                    </div>
                @else
                    {{-- O que já está no cadastro fica em verde, como no cadastro novo. --}}
                    <dl class="validation-person-data__campos">
                        <div class="is-ok"><dt><x-icon name="check-circle" /> Nome completo</dt><dd>{{ $dadosIdent['nome'] }}</dd></div>
                        @if ($dadosIdent['nome_social'])<div class="is-ok"><dt><x-icon name="check-circle" /> Nome social</dt><dd>{{ $dadosIdent['nome_social'] }}</dd></div>@endif
                        <div @class(['is-ok' => $dadosIdent['nascimento']])><dt>@if ($dadosIdent['nascimento'])<x-icon name="check-circle" /> @endif Data de nascimento</dt><dd>{{ $dadosIdent['nascimento'] ?? 'Não informada' }}</dd></div>
                        <div @class(['is-ok' => $dadosIdent['cpf']])><dt>@if ($dadosIdent['cpf'])<x-icon name="check-circle" /> @endif CPF</dt><dd>{{ $dadosIdent['cpf'] ? ($documentRevealed ? $dadosIdent['cpf'] : \App\Support\NormalizadorBusca::mascararDocumento($dadosIdent['cpf'])) : 'Não informado' }}</dd></div>
                        <div @class(['is-ok' => $dadosIdent['telefone']])><dt>@if ($dadosIdent['telefone'])<x-icon name="check-circle" /> @endif Telefone</dt><dd>{{ $dadosIdent['telefone'] ?? 'Não informado' }}</dd></div>
                        <div @class(['is-ok' => $dadosIdent['email']])><dt>@if ($dadosIdent['email'])<x-icon name="check-circle" /> @endif E-mail</dt><dd>{{ $dadosIdent['email'] ?? 'Não informado' }}</dd></div>
                    </dl>
                @endif
            </section>
        @endif

        @if (! $quickPersonRegistered)
            @php($tipoCadastro = $this->tipoDoCadastro())
            {{-- Tipo de acesso da pessoa já cadastrada (pedido do PO de 13/11/2026). --}}
            <section class="validation-person-data validation-tipo-acesso" aria-labelledby="tipo-acesso-titulo" wire:key="tipo-acesso-{{ $currentPessoaId }}">
                <header>
                    <div>
                        <h3 id="tipo-acesso-titulo">Tipo de acesso</h3>
                        <p>{{ $tipoCadastro['travado'] ? $tipoCadastro['detalhe'] : 'Esta pessoa não tem vínculo. Escolha o tipo: ao liberar a entrada, ele fica gravado no cadastro.' }}</p>
                    </div>
                    @if ($tipoCadastro['travado'])
                        <x-ui.badge variant="success" icon="check-circle">{{ $tipoCadastro['rotulo'] }}</x-ui.badge>
                    @endif
                </header>
                @unless ($tipoCadastro['travado'])
                    <div class="registration-fields">
                        <x-ui.select id="tipo-atendimento" label="Tipo de acesso" wire:model.live="tipoAtendimento" :error="$errors->first('tipoAtendimento')" required>
                            <option value="">Escolha…</option>
                            @foreach (\App\Livewire\AccessValidation::TIPOS_ATENDIMENTO as $valorTipo => $rotuloTipo)
                                <option value="{{ $valorTipo }}">{{ $rotuloTipo }}</option>
                            @endforeach
                        </x-ui.select>
                        @if ($tipoAtendimento === 'turista')
                            <p class="validation-tipo-acesso__prazo"><x-icon name="clock" /> Turista: autorização de 24 h a partir da liberação.</p>
                        @elseif ($tipoAtendimento !== '')
                            <x-ui.field id="valido-ate" type="datetime-local" label="Válido até" wire:model.blur="validoAte" :min="now()->format('Y-m-d\TH:i')" :max="now()->addDays(\App\Models\AutorizacaoAcesso::PRAZO_MAXIMO_DIAS)->format('Y-m-d\TH:i')" help="Data e hora em que a autorização acaba (até {{ \App\Models\AutorizacaoAcesso::PRAZO_MAXIMO_DIAS }} dias)." :error="$errors->first('validoAte')" required />
                        @endif
                        @if ($tipoAtendimento === 'visitante')
                            <x-ui.field id="imovel-atendimento" label="Código do imóvel" wire:model.blur="imovelAtendimento" placeholder="SRA-A-102" help="Imóvel que o visitante vai visitar." :error="$errors->first('imovelAtendimento')" required />
                            <x-ui.field id="responsavel-atendimento" label="Responsável pelo visitante" wire:model.blur="responsavelAtendimento" help="Quem recebe o visitante no imóvel." :error="$errors->first('responsavelAtendimento')" required />
                        @endif
                    </div>
                @endunless
            </section>
        @endif

        @foreach ($currentPerson['alerts'] as $alerta)
            <x-ui.alert :variant="$alerta['tone']" title="Atenção">{{ $alerta['text'] }}</x-ui.alert>
        @endforeach

        @php($protectedFiles = $this->currentProtectedFiles)
        @if ($protectedFiles['pre_registration_id'])
            <x-protected-file-review
                :document-link="$protectedFiles['document']"
                :document-back-link="$protectedFiles['document_back']"
                :document-portrait-link="$protectedFiles['document_portrait']"
                :selfie-link="$protectedFiles['selfie']"
                :id="'validation-'.$protectedFiles['pre_registration_id']"
            />
        @endif
        @endif
    </section>
    @endif

    @if ($passo === 'foto')
    {{-- ===================== 2. FOTO ===================== --}}
    <section class="validation-section" aria-labelledby="validation-photo-title">
        <header class="validation-section__header">
            <div>
                <h2 id="validation-photo-title">Foto da pessoa</h2>
                <p>Foto atual do rosto, separada do documento. Não é prova de vida nem reconhecimento facial.</p>
            </div>
        </header>

        @if ($quickRegistrationOpen)
            @php($cameras = $this->contextoCameras())
            @php($existente = $this->cadastroExistenteResumo())
            <div class="foto-pessoa" x-data="fotoPessoaCamera({ implantacaoId: @js($cameras['implantacao']), postoId: @js($cameras['posto']) })" x-on:cadastro-rapido-encerrado.window="encerrar()">
                <div class="foto-pessoa__resumo">
                    <strong>{{ $quickName }}</strong>
                    <small>CPF {{ \App\Support\NormalizadorBusca::mascararDocumento($quickDocument) }} · {{ ucfirst($quickAccessType) }}{{ $quickPropertyCode ? ' · '.$quickPropertyCode : '' }}</small>
                </div>
                @php($selfiePre = $this->selfieDoPreCadastro())
                @if ($selfiePre)
                    <p class="foto-pessoa__aviso">Selfie do pré-cadastro: será a foto do cadastro. Abra a câmera só se ela não servir.</p>
                @elseif ($quickPessoaExistenteId && ($existente['tem_foto'] ?? false))
                    <p class="foto-pessoa__aviso">Esta pessoa já tem foto no cadastro. Tire outra só se ela não servir.</p>
                @endif
                @include('livewire.partials.foto-pessoa-camera', [
                    'erro' => 'fotoPessoa',
                    'rotuloAbrir' => $selfiePre ? 'Tirar outra foto' : null,
                    'fotoAtual' => $selfiePre && auth()->user()?->hasPermission('arquivos.sensiveis.visualizar')
                        ? ['url' => route('protected-files.show', ['arquivo' => $selfiePre, 'contexto' => 'validacao']), 'alt' => 'Selfie enviada no pré-cadastro', 'legenda' => 'Selfie do pré-cadastro']
                        : null,
                ])
                @if ($quickPessoaExistenteId && ($existente['tem_foto'] ?? false))
                    <x-ui.checkbox id="quick-substituir-foto" label="Substituir a foto atual do cadastro" description="Sem marcar, a foto existente é mantida e esta captura é descartada. A anterior fica no histórico." wire:model="quickSubstituirFoto" />
                @endif
            </div>
        @else
            @php($camerasIdentificacao = $this->contextoCameras())
            <div
                class="foto-pessoa"
                wire:key="foto-identificacao-{{ $currentPessoaId }}"
                x-data="fotoPessoaCamera({ implantacaoId: @js($camerasIdentificacao['implantacao']), postoId: @js($camerasIdentificacao['posto']), propriedade: 'fotoIdentificacao', metodoRefazer: 'refazerFotoIdentificacao' })"
                x-on:foto-identificacao-encerrada.window="encerrar()"
            >
                <div class="foto-pessoa__resumo">
                    <strong>{{ $currentPerson['name'] }}</strong>
                    @if ($currentPerson['has_photo'])
                        <small>Foto do cadastro. Se não estiver parecida, tire uma nova: ela substitui a atual no Concluir e a anterior fica no histórico. Não é enviada ao terminal.</small>
                    @else
                        <small>Pessoa sem foto: a foto vai para o cadastro no Concluir e aparece nos próximos atendimentos. Não é enviada ao terminal.</small>
                    @endif
                </div>
                @php($fotoAtual = $currentPerson['photo_url'] ? ['url' => $currentPerson['photo_url'], 'alt' => 'Foto atual de '.$currentPerson['name'], 'legenda' => $currentPerson['photo_origin'] ?? 'Foto do cadastro'] : null)
                @if ($currentPerson['can_take_photo'])
                    @include('livewire.partials.foto-pessoa-camera', ['erro' => 'fotoIdentificacao', 'rotuloAbrir' => $currentPerson['has_photo'] ? 'Tirar nova foto' : null, 'fotoAtual' => $fotoAtual])
                @elseif ($fotoAtual)
                    <figure class="foto-pessoa__previa foto-pessoa__atual">
                        <img src="{{ $fotoAtual['url'] }}" alt="{{ $fotoAtual['alt'] }}">
                        <figcaption><x-ui.badge variant="success" icon="check-circle">{{ $fotoAtual['legenda'] }}</x-ui.badge></figcaption>
                    </figure>
                @endif
            </div>
        @endif
    </section>
    @endif

    @if ($passo === 'placa')
    {{-- ===================== 3. PLACA ===================== --}}
    <section class="validation-section" aria-labelledby="validation-vehicle-title">
        <header class="validation-section__header">
            <div>
                <h2 id="validation-vehicle-title">Veio de carro?</h2>
                <p>Se veio, confira a placa. Se veio a pé, siga em frente.</p>
            </div>
        </header>

        <div class="atendimento-escolha" role="group" aria-label="Veio de carro?">
            <button type="button" @class(['ui-button', 'ui-button--lg', 'ui-button--primary' => $veioDeCarro === true, 'ui-button--secondary' => $veioDeCarro !== true]) wire:click="escolherCarro" aria-pressed="{{ $veioDeCarro === true ? 'true' : 'false' }}">
                <span class="ui-button__content"><x-icon name="car" /> Sim, de carro</span>
            </button>
            <button type="button" @class(['ui-button', 'ui-button--lg', 'ui-button--primary' => $veioDeCarro === false, 'ui-button--secondary' => $veioDeCarro !== false]) wire:click="$set('veioDeCarro', false)" aria-pressed="{{ $veioDeCarro === false ? 'true' : 'false' }}">
                <span class="ui-button__content">Não, veio a pé</span>
            </button>
        </div>
        @error('veioDeCarro')<small class="ui-field__message ui-field__message--error" role="alert">{{ $message }}</small>@enderror

        @if ($veioDeCarro === true)
        @php($placaDigitada = \App\Support\NormalizadorBusca::documento($placaInformada))
        @php($placasCadastro = collect($currentPerson['vehicles'] ?? [])->map(fn ($v) => \App\Support\NormalizadorBusca::documento($v['plate']))->all())
        @php($captura = $this->capturaPlaca)
        @php($cameras = $this->camerasPlaca)
        <div @class(['validation-plate', 'validation-plate--com-camera' => $cameras !== []]) data-rolar="placa-veiculo">
            @if ($cameras !== [])
            {{-- Câmera de placas do posto (Integrações › Câmeras de placas) --}}
            <div class="validation-plate__capture">
                <div class="validation-plate__visor">
                    @if ($captura)
                        <figure @class(['validation-plate__image', 'is-simulated' => $captura['simulada']])>
                            @if ($captura['url'])
                                <img src="{{ $captura['url'] }}" alt="Imagem capturada pela câmera {{ $captura['camera'] }}" referrerpolicy="no-referrer">
                            @else
                                <span class="validation-plate__hidden">Imagem guardada como evidência. Seu perfil não permite exibi-la.</span>
                            @endif
                        </figure>
                    @else
                        <div class="validation-plate__aguardando" wire:loading.class="is-loading" wire:target="capturarPlaca,escolherCarro">
                            <x-icon name="car" />
                            <span wire:loading.remove wire:target="capturarPlaca,escolherCarro">Imagem da câmera aparece aqui</span>
                            <span wire:loading wire:target="capturarPlaca,escolherCarro">Capturando a imagem…</span>
                        </div>
                    @endif
                </div>

                @if ($capturaPlacaFalha)
                    <x-ui.alert variant="warning" title="Imagem não capturada">{{ $capturaPlacaFalha }}</x-ui.alert>
                @endif

                <div class="validation-plate__camera">
                    @if (count($cameras) > 1)
                        <x-ui.select id="validation-plate-camera" label="Câmera de placas" wire:model="cameraPlacaId" :error="$errors->first('cameraPlacaId')">
                            <option value="">Escolha a câmera</option>
                            @foreach ($cameras as $camera)
                                <option value="{{ $camera['id'] }}">{{ $camera['nome'] }} · {{ $camera['ponto'] }}{{ $camera['simulador'] ? ' (SIMULADOR)' : '' }}</option>
                            @endforeach
                        </x-ui.select>
                    @else
                        <p class="validation-plate__camera-nome">
                            <strong>{{ $cameras[0]['nome'] }}{{ $cameras[0]['simulador'] ? ' (SIMULADOR)' : '' }}</strong>
                            <small>{{ $cameras[0]['ponto'] }}</small>
                        </p>
                    @endif
                    @if ($captura)
                        <p class="validation-plate__camera-nome">
                            @if ($captura['simulada'])<x-ui.badge variant="danger">SIMULADOR · imagem sintética</x-ui.badge>@endif
                            <small>{{ $captura['camera'] }} · {{ $captura['ponto'] ?? 'Ponto não informado' }} · capturada em {{ $captura['capturada_em'] }}</small>
                        </p>
                    @endif
                    <x-ui.button :variant="$captura ? 'secondary' : 'primary'" wire:click="{{ $captura ? 'descartarCapturaPlaca' : 'capturarPlaca' }}" wire:loading.attr="disabled" wire:target="capturarPlaca,escolherCarro,descartarCapturaPlaca">
                        <x-icon name="camera" /> {{ $captura ? 'Capturar de novo' : 'Capturar placa' }}
                    </x-ui.button>
                </div>
            </div>
            @endif

            <div class="validation-plate__reading">
                <x-ui.field id="validation-plate" label="Placa do veículo" wire:model.live.debounce.400ms="placaInformada" placeholder="ABC1D23" maxlength="8" :error="$errors->first('placaInformada')" :help="$cameras !== [] ? 'Confira na imagem da câmera e digite a placa.' : 'Este posto não tem câmera de placas: digite a placa informada pelo motorista.'" />
                @if ($placaDigitada !== '')
                    @if (in_array($placaDigitada, $placasCadastro, true))
                        <x-ui.badge variant="success" icon="check-circle">Placa confere com o cadastro da pessoa</x-ui.badge>
                    @elseif ($currentPerson['selected'] ?? false)
                        <x-ui.badge variant="warning" icon="alert">Placa não vinculada a esta pessoa</x-ui.badge>
                    @endif
                @endif
                @if (($currentPerson['vehicles'] ?? []) !== [])
                    <div class="validation-plate__registered">
                        <h3>Veículos no cadastro <small>toque para usar</small></h3>
                        <ul>
                            @foreach ($currentPerson['vehicles'] as $vehicle)
                                @php($placaVeiculo = \App\Support\NormalizadorBusca::documento($vehicle['plate']))
                                <li>
                                    <button type="button" @class(['validation-plate__veiculo', 'is-selected' => $placaVeiculo === $placaDigitada]) wire:click="$set('placaInformada', '{{ $placaVeiculo }}')">
                                        <span class="validation-plate__chapa">{{ $vehicle['plate'] }}</span>
                                        <span class="validation-plate__descricao">{{ $vehicle['description'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($cameras === [])
                    <p class="validation-plate__note">Câmeras de placas são cadastradas pela administração em Integrações › Câmeras de placas e aparecem aqui sozinhas.</p>
                @endif
            </div>
        </div>
        @endif
    </section>
    @endif

    @if ($passo === 'pagamento')
    {{-- ===================== 4. PAGAMENTO (só turista) ===================== --}}
    <section class="validation-section" aria-labelledby="validation-contribution-title">
        <header class="validation-section__header">
            <div>
                <h2 id="validation-contribution-title">Contribuição do turista</h2>
                <p>O pagamento vai para o caixa do seu posto e não autoriza a entrada por si só.</p>
            </div>
            <x-ui.badge :variant="$this->caixaAberto ? 'success' : 'warning'">{{ $this->caixaAberto ? 'Caixa aberto' : 'Caixa fechado para você' }}</x-ui.badge>
        </header>

        <fieldset class="validation-contribution-options">
            <legend class="sr-only">Selecione a situação da contribuição</legend>
            <x-ui.radio id="contribution-yes" name="contribution" value="yes" label="Contribui" description="Entrada paga neste acesso" :checked="$contribution === 'yes'" wire:model.live="contribution" />
            <x-ui.radio id="contribution-no" name="contribution" value="no" label="Não contribui" description="Entrada sem pagamento" :checked="$contribution === 'no'" wire:model.live="contribution" />
            <x-ui.radio id="contribution-exempt" name="contribution" value="exempt" label="Isento" description="Pessoa isenta de contribuição" :checked="$contribution === 'exempt'" wire:model.live="contribution" />
        </fieldset>

        @php($valorContribuicao = \App\Services\ContribuicoesAtendimento::valorPadrao())
        @php($valorFormatado = 'R$ '.number_format((float) $valorContribuicao, 2, ',', '.'))
        @php($duplicado = $this->ticketDuplicado)

        @if ($contribution === 'yes')
            <div class="validation-contribution-details">
                <div class="validation-contribution-fields">
                    <x-ui.field id="contribution-value" label="Valor da contribuição" :value="$valorFormatado" readonly />
                    <x-ui.select id="payment-method" label="Forma de pagamento" wire:model.live="paymentMethod" :error="$errors->first('paymentMethod')">
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão</option>
                    </x-ui.select>
                    <x-ui.field id="received-from" label="Recebido de" :value="$this->resumoAtendimento()['pessoa']" readonly />
                </div>

                <aside class="validation-payment-summary" aria-label="Resumo do pagamento">
                    <span>Resumo do pagamento</span>
                    <dl>
                        <div><dt>Valor</dt><dd>{{ $valorFormatado }}</dd></div>
                        <div><dt>Desconto</dt><dd>R$ 0,00</dd></div>
                        <div class="is-total"><dt>Total</dt><dd>{{ $valorFormatado }}</dd></div>
                    </dl>
                    <small>Operador: {{ auth()->user()?->name ?? 'Operador identificado na sessão' }}</small>
                </aside>
            </div>
        @elseif ($contribution === 'no')
            <x-ui.alert variant="warning" title="Sem contribuição">
                A decisão será registrada sem pagamento. Isso não altera as condições de autorização.
            </x-ui.alert>
        @else
            <x-ui.alert variant="info" title="Isenção selecionada">
                A isenção será registrada no atendimento e poderá ser consultada posteriormente.
            </x-ui.alert>
        @endif

        <div class="validation-ticket">
            <x-ui.field
                id="ticket-number"
                label="Número do ticket de acesso"
                wire:model.live.debounce.400ms="ticketNumero"
                maxlength="30"
                inputmode="text"
                autocomplete="off"
                :required="$contribution === 'yes'"
                :help="$contribution === 'yes' ? 'Obrigatório para contribuição paga. Digite exatamente como no ticket, inclusive zeros à esquerda.' : 'Opcional para não contribuinte ou isento.'"
                :error="$errors->first('ticketNumero')"
            />
            @if ($duplicado)
                <x-ui.alert variant="warning" title="Ticket já registrado">
                    Este número aparece em outro atendimento ({{ $duplicado['quando'] }}{{ $duplicado['protocolo'] ? ' · '.$duplicado['protocolo'] : '' }}). O SDV não valida série fiscal: confira o ticket físico.
                    <label class="validation-ticket__confirm"><input type="checkbox" wire:model.live="ticketDuplicadoConfirmado"> Conferi e quero registrar mesmo assim</label>
                </x-ui.alert>
            @endif
        </div>
    </section>
    @endif

    @if ($passo === 'concluir')
    {{-- ===================== 5. CONCLUIR ===================== --}}
    @php($resumo = $this->resumoAtendimento())
    <section class="validation-section" aria-labelledby="validation-summary-title">
        <header class="validation-section__header">
            <div>
                <h2 id="validation-summary-title">Conferir e concluir</h2>
                <p>Confira o resumo. O cadastro, a foto e o atendimento são gravados ao escolher a decisão.</p>
            </div>
        </header>

        <dl class="atendimento-resumo">
            <div><dt>Pessoa</dt><dd>{{ $resumo['pessoa'] }} · {{ $resumo['tipo'] }}{{ $resumo['novo'] ? ' · cadastro novo' : '' }}</dd>@unless ($protocol)<button type="button" class="sensitive-reveal" wire:click="irParaPasso('dados')">Alterar</button>@endunless</div>
            @if (isset($passos['foto']) || $resumo['novo'])
                <div><dt>Foto</dt><dd>{{ $resumo['foto'] }}</dd>@if (! $protocol && isset($passos['foto']))<button type="button" class="sensitive-reveal" wire:click="irParaPasso('foto')">Alterar</button>@endif</div>
            @endif
            @if ($resumo['autorizacao'])
                <div><dt>Autorização</dt><dd>{{ $resumo['autorizacao'] }}</dd>@unless ($protocol)<button type="button" class="sensitive-reveal" wire:click="irParaPasso('dados')">Alterar</button>@endunless</div>
            @endif
            <div><dt>Veículo</dt><dd>{{ $resumo['veiculo'] }}</dd>@unless ($protocol)<button type="button" class="sensitive-reveal" wire:click="irParaPasso('placa')">Alterar</button>@endunless</div>
            <div><dt>Pagamento</dt><dd>{{ $resumo['pagamento'] ?? 'Sem contribuição (só turista contribui)' }}</dd>@if (! $protocol && $resumo['pagamento'] !== null)<button type="button" class="sensitive-reveal" wire:click="irParaPasso('pagamento')">Alterar</button>@endif</div>
        </dl>

        @if ($resumo['novo'])
            <x-ui.alert variant="warning" title="Cadastro não significa entrada liberada">
                O cadastro rápido não cria autorização. A entrada depende de uma autorização válida, conferida separadamente.
            </x-ui.alert>

            @php($terminaisRapido = $this->terminaisCadastroRapido())
            @if ($terminaisRapido->isNotEmpty())
                <div class="quick-registration__terminal">
                    <x-ui.checkbox id="enviar-facial" label="Enviar também para o terminal facial" description="Envia o cadastro (e a foto, quando habilitado) ao terminal. Não abre cancela nem catraca." wire:model.live="enviarFacial" />
                    @if ($enviarFacial)
                        <x-ui.select id="quick-terminal" label="Terminal facial de destino" wire:model.live="quickTerminalId" :error="$errors->first('quickTerminalId')">
                            <option value="">Selecione o terminal</option>
                            @foreach ($terminaisRapido as $terminal)
                                <option value="{{ $terminal['id'] }}">{{ $terminal['nome'] }} — {{ $terminal['ponto'] }}{{ $terminal['do_posto'] ? ' (seu posto)' : '' }}{{ $terminal['simulador'] ? ' [SIMULADOR]' : '' }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.checkbox id="quick-terminal-confirmado" label="Confirmo o terminal escolhido" wire:model.live="quickTerminalConfirmado" />
                        @error('quickTerminalConfirmado') <small class="ui-field__error" role="alert">{{ $message }}</small> @enderror
                    @endif
                </div>
            @endif
        @endif

        @if ($quickResultado && ! $quickRegistrationOpen)
            <section class="quick-resultado" aria-label="Resultado do cadastro rápido">
                @foreach (['cadastro' => 'Cadastro no SDV', 'foto' => 'Foto da pessoa'] as $chave => $rotulo)
                    <x-ui.alert :variant="$quickResultado[$chave]['variant']" :title="$rotulo.': '.$quickResultado[$chave]['title']">{{ $quickResultado[$chave]['message'] }}</x-ui.alert>
                @endforeach
                @if ($quickResultado['facial'])
                    <x-ui.alert :variant="$quickResultado['facial']['variant']" :title="'Terminal facial — cadastro: '.$quickResultado['facial']['title']">
                        {{ $quickResultado['facial']['message'] }}
                        @foreach ($quickResultado['facial']['avisos'] ?? [] as $aviso)<br>{{ $aviso }}@endforeach
                    </x-ui.alert>
                    @if ($quickResultado['facial']['foto'] ?? null)
                        <x-ui.alert :variant="$quickResultado['facial']['foto']['variant']" :title="'Terminal facial — foto: '.$quickResultado['facial']['foto']['title']">{{ $quickResultado['facial']['foto']['message'] }}</x-ui.alert>
                    @endif
                @endif
            </section>
        @endif

        @if ($fotoIdentificacaoSalva)
            <x-ui.alert variant="success" title="Foto adicionada ao cadastro">
                A foto ficou guardada de forma protegida no cadastro da pessoa. Ela não é enviada ao terminal facial.
            </x-ui.alert>
        @endif

        <label class="validation-notes-field" for="validation-notes">
            <span>Observação do atendimento (opcional)</span>
            <textarea id="validation-notes" wire:model.live="notes" maxlength="200" rows="3" placeholder="Digite uma observação sobre este acesso…" @class(['is-invalid' => $errors->has('notes')]) @disabled($protocol)></textarea>
            <small><span>{{ mb_strlen($notes) }}</span>/200 caracteres</small>
            @error('notes') <strong role="alert">{{ $message }}</strong> @enderror
        </label>
    </section>

    <section class="validation-decision" aria-labelledby="validation-decision-title">
        <header>
            <div>
                <span>Decisão final</span>
                <h2 id="validation-decision-title">O que deseja fazer com este atendimento?</h2>
            </div>
            <x-ui.badge variant="info">Protótipo P06 + P07</x-ui.badge>
        </header>

        @if ($protocol)
            <x-ui.alert :variant="$feedback['variant'] ?? 'info'" :title="'Atendimento registrado: '.$protocol">
                {{ $feedback['title'] ?? '' }}. {{ $feedback['message'] ?? '' }}
            </x-ui.alert>
            @error('atendimento')<small class="ui-field__message ui-field__message--error" role="alert">{{ $message }}</small>@enderror
            <div class="validation-decision__actions">
                <div>
                    <x-ui.button variant="primary" wire:click="startNewValidation">Iniciar nova validação</x-ui.button>
                    <small>Limpa a tela para atender a próxima pessoa.</small>
                </div>
            </div>
        @else
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
                            @foreach ($denialReasonOptions as $opcao)
                                <option value="{{ $opcao['codigo'] }}">{{ $opcao['rotulo'] }}</option>
                            @endforeach
                        </x-ui.select>

                        <label class="validation-notes-field" for="denial-details">
                            <span>Detalhes adicionais</span>
                            <textarea id="denial-details" wire:model="denialDetails" maxlength="200" rows="3" placeholder="Explique a negativa, se necessário…"></textarea>
                        </label>
                    </div>

                    <x-slot:confirm>
                        <form method="dialog">
                            <x-ui.button type="submit" variant="danger" wire:click="concluir('negar')" wire:loading.attr="disabled" wire:target="concluir">
                                Confirmar negativa
                            </x-ui.button>
                        </form>
                    </x-slot:confirm>
                </x-ui.modal>
                <small>Registra a negativa sem enviar comando.</small>
            </div>

            <div>
                <x-ui.button variant="warning" wire:click="concluir('pendente')" wire:loading.attr="disabled" wire:target="concluir">
                    Salvar sem liberar
                </x-ui.button>
                <small>Grava o atendimento para continuar depois.</small>
            </div>

            <div>
                <x-ui.button variant="success" wire:click="concluir('liberar')" wire:loading.attr="disabled" wire:target="concluir">
                    Liberar entrada
                </x-ui.button>
                <small>Exige autorização válida. Simula a liberação, sem acionar portão real.</small>
            </div>
        </div>
        @endif

        <p><x-icon name="info" /> P06 + P07 em demonstração: cadastro rápido não autoriza entrada e nenhuma das ações desta página envia comandos para equipamentos físicos.</p>
    </section>
    @endif

    @unless ($protocol)
        <footer @class(['atendimento-navegacao', 'is-final' => $ultimoPasso])>
            @if ($indicePasso > 0)
                <x-ui.button variant="secondary" wire:click="voltar">Voltar</x-ui.button>
            @else
                <span></span>
            @endif
            @unless ($ultimoPasso)
                <x-ui.button variant="primary" size="lg" wire:click="continuar" wire:loading.attr="disabled" wire:target="continuar,fotoPessoa,fotoIdentificacao">
                    Continuar
                </x-ui.button>
            @endunless
        </footer>
    @endunless
    </div>
</div>

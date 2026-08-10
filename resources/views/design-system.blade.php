<x-layouts.guest title="Catálogo de componentes">
    <div class="component-catalog">
        <header class="component-catalog__header">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="Voltar ao Dashboard">
                <span class="brand__mark" aria-hidden="true">SDV</span>
                <span><strong>SDV Access</strong><small>Santa Rita</small></span>
            </a>
            <div>
                <x-ui.badge variant="success">P04 · Concluído</x-ui.badge>
                <h1>Componentes compartilhados</h1>
                <p>Catálogo local para testar as peças visuais antes de usá-las nas telas do sistema.</p>
            </div>
        </header>

        <main class="component-catalog__content">
            <section class="component-section" aria-labelledby="buttons-title">
                <header>
                    <span>DS-CMP-003 e 004</span>
                    <h2 id="buttons-title">Botões e grupos de ações</h2>
                    <p>Cores diferentes representam intenção, prioridade e risco.</p>
                </header>

                <x-ui.card>
                    <div class="component-row">
                        <x-ui.button>Salvar alterações</x-ui.button>
                        <x-ui.button variant="secondary">Voltar</x-ui.button>
                        <x-ui.button variant="success">
                            <x-slot:icon><x-icon name="check-circle" /></x-slot:icon>
                            Validar e liberar
                        </x-ui.button>
                        <x-ui.button variant="warning">Salvar sem liberar</x-ui.button>
                        <x-ui.button variant="danger">Negar entrada</x-ui.button>
                        <x-ui.button variant="ghost">Cancelar</x-ui.button>
                    </div>
                    <div class="component-row component-row--secondary">
                        <x-ui.button size="sm">Botão pequeno</x-ui.button>
                        <x-ui.button :loading="true" loading-label="Salvando…">Salvar</x-ui.button>
                        <x-ui.button disabled>Desabilitado</x-ui.button>
                        <x-ui.button variant="secondary" :icon-only="true" aria-label="Fechar">
                            <x-slot:icon><x-icon name="x" /></x-slot:icon>
                            <span class="sr-only">Fechar</span>
                        </x-ui.button>
                    </div>
                </x-ui.card>
            </section>

            <section class="component-section" aria-labelledby="fields-title">
                <header>
                    <span>DS-CMP-005</span>
                    <h2 id="fields-title">Campos de formulário</h2>
                    <p>O rótulo permanece visível e o erro orienta como corrigir.</p>
                </header>

                <x-ui.card>
                    <div class="component-fields-grid">
                        <x-ui.field
                            id="demo-name"
                            label="Nome completo"
                            placeholder="Digite o nome da pessoa"
                            help="Use o nome apresentado no documento."
                            required
                        />
                        <x-ui.field
                            id="demo-document"
                            label="Documento"
                            value="123"
                            error="Informe um CPF válido com 11 números."
                            required
                        />
                        <x-ui.field id="demo-property" label="Imóvel" value="Bloco B — Apto 304" readonly />
                        <x-ui.field id="demo-disabled" label="Credencial" value="Aguardando cadastro" disabled />
                    </div>
                </x-ui.card>
            </section>

            <section class="component-section" aria-labelledby="choices-title">
                <header>
                    <span>DS-CMP-006 e 009</span>
                    <h2 id="choices-title">Seleções e escolhas</h2>
                    <p>Cada tipo de controle representa uma decisão diferente.</p>
                </header>

                <x-ui.card>
                    <div class="component-fields-grid">
                        <x-ui.select id="demo-access-type" label="Tipo de acesso" help="Escolha uma opção da lista." required>
                            <option value="">Selecione</option>
                            <option value="resident">Morador</option>
                            <option value="tenant">Inquilino</option>
                            <option value="visitor">Visitante</option>
                            <option value="provider">Prestador</option>
                        </x-ui.select>

                        <fieldset class="ui-choice-group">
                            <legend>Documentos apresentados</legend>
                            <x-ui.checkbox id="demo-cpf" label="CPF" description="Documento principal conferido." checked />
                            <x-ui.checkbox id="demo-photo" label="Foto" description="Imagem facial ainda pendente." />
                        </fieldset>

                        <fieldset class="ui-choice-group">
                            <legend>Decisão sobre a contribuição</legend>
                            <x-ui.radio id="demo-contribution-yes" name="demo-contribution" value="yes" label="Contribui" description="Registrar pagamento neste acesso." checked />
                            <x-ui.radio id="demo-contribution-no" name="demo-contribution" value="no" label="Não contribui" description="Nenhum pagamento será registrado." />
                        </fieldset>

                        <div class="ui-switch-group">
                            <x-ui.switch id="demo-notification" label="Enviar notificação" description="Avisa o responsável quando a pessoa chegar." checked />
                            <x-ui.switch id="demo-auto-release" label="Liberação automática" description="Desabilitada porque exige regra e permissão." disabled />
                        </div>
                    </div>
                </x-ui.card>
            </section>

            <section class="component-section" aria-labelledby="navigation-title">
                <header>
                    <span>DS-CMP-020 a 023</span>
                    <h2 id="navigation-title">Navegação e etapas</h2>
                    <p>O usuário entende onde está, o que já concluiu e o que ainda falta.</p>
                </header>

                <div class="component-navigation-grid">
                    <x-ui.card title="Caminho da página" description="Localização dentro do sistema">
                        <x-ui.breadcrumb>
                            <x-ui.breadcrumb-item :href="route('dashboard')">Dashboard</x-ui.breadcrumb-item>
                            <x-ui.breadcrumb-item href="#">Cadastros</x-ui.breadcrumb-item>
                            <x-ui.breadcrumb-item current>Pessoas</x-ui.breadcrumb-item>
                        </x-ui.breadcrumb>
                    </x-ui.card>

                    <x-ui.card title="Etapas do cadastro" description="Progresso sem esconder erros">
                        <x-ui.stepper
                            :current="3"
                            :steps="[
                                ['label' => 'Tipo de acesso', 'description' => 'Concluído'],
                                ['label' => 'Dados pessoais', 'description' => 'Concluído'],
                                ['label' => 'Vínculo', 'description' => 'Etapa atual'],
                                ['label' => 'Credenciais', 'description' => 'Ainda não iniciado'],
                            ]"
                        />
                    </x-ui.card>
                </div>

                <x-ui.card title="Abas do cadastro" description="Visões equivalentes da mesma pessoa" class="component-tabs-card">
                    <x-ui.tabs default="summary">
                        <div class="ui-tab-list" role="tablist" aria-label="Dados da pessoa">
                            <x-ui.tab id="summary" label="Resumo" />
                            <x-ui.tab id="vehicles" label="Veículos" />
                            <x-ui.tab id="history" label="Histórico" />
                        </div>
                        <x-ui.tab-panel id="summary">
                            <strong>Marcos Vinicius da Silva</strong>
                            <p>Morador · Bloco A — Apto 102 · Cadastro ativo</p>
                        </x-ui.tab-panel>
                        <x-ui.tab-panel id="vehicles">
                            <strong>1 veículo vinculado</strong>
                            <p>ABC1D23 · Toyota Corolla · Prata</p>
                        </x-ui.tab-panel>
                        <x-ui.tab-panel id="history">
                            <strong>Último acesso às 08:42</strong>
                            <p>Entrada liberada pela Portaria Principal.</p>
                        </x-ui.tab-panel>
                    </x-ui.tabs>
                </x-ui.card>

                <x-ui.pagination :current="2" :total="5" :from="21" :to="40" :total-items="96" />
            </section>

            <section class="component-section" aria-labelledby="status-title">
                <header>
                    <span>DS-CMP-011 e 012</span>
                    <h2 id="status-title">Situações e avisos</h2>
                    <p>Cor, ícone e texto trabalham juntos; a cor nunca é o único sinal.</p>
                </header>

                <x-ui.card>
                    <div class="component-row">
                        <x-ui.badge>Rascunho</x-ui.badge>
                        <x-ui.badge variant="info" icon="info">Em análise</x-ui.badge>
                        <x-ui.badge variant="success" icon="check-circle">Cadastro ativo</x-ui.badge>
                        <x-ui.badge variant="warning" icon="alert">Documento pendente</x-ui.badge>
                        <x-ui.badge variant="danger" icon="alert">Acesso bloqueado</x-ui.badge>
                        <x-ui.badge variant="category">Visitante</x-ui.badge>
                    </div>

                    <div class="component-alerts">
                        <x-ui.alert title="Informação operacional">A conferência deve ser concluída antes da decisão.</x-ui.alert>
                        <x-ui.alert variant="success" title="Cadastro localizado">Pessoa e vínculo encontrados com sucesso.</x-ui.alert>
                        <x-ui.alert variant="warning" title="Atenção necessária">O documento vence em menos de 30 dias.</x-ui.alert>
                        <x-ui.alert variant="danger" title="Entrada não autorizada">O vínculo com o imóvel está suspenso.</x-ui.alert>
                    </div>
                </x-ui.card>
            </section>

            <section class="component-section" aria-labelledby="cards-title">
                <header>
                    <span>DS-CMP-014 e 016</span>
                    <h2 id="cards-title">Cartões e ausência de dados</h2>
                    <p>Os cartões agrupam assuntos; o estado vazio explica o próximo passo.</p>
                </header>

                <div class="component-cards-grid">
                    <x-ui.card title="Atendimento atual" description="Validação iniciada às 14:32">
                        <dl class="component-summary">
                            <div><dt>Pessoa</dt><dd>Camila Andrade</dd></div>
                            <div><dt>Destino</dt><dd>Bloco B — Apto 304</dd></div>
                        </dl>
                        <x-slot:footer>
                            <x-ui.button variant="secondary" size="sm">Ver detalhes</x-ui.button>
                        </x-slot:footer>
                    </x-ui.card>

                    <x-ui.card variant="critical" title="Integração indisponível" description="Controladora do Portão de Serviço">
                        <p class="component-card-copy">Última comunicação confirmada às 14:52.</p>
                        <x-slot:footer>
                            <x-ui.button variant="danger" size="sm">Ver ocorrência</x-ui.button>
                        </x-slot:footer>
                    </x-ui.card>

                    <x-ui.card>
                        <x-ui.empty-state
                            title="Nenhum pré-cadastro encontrado"
                            description="Revise os filtros ou inicie uma nova solicitação autorizada."
                        >
                            <x-slot:action>
                                <x-ui.button size="sm">Novo pré-cadastro</x-ui.button>
                            </x-slot:action>
                        </x-ui.empty-state>
                    </x-ui.card>
                </div>
            </section>

            <section class="component-section" aria-labelledby="table-title">
                <header>
                    <span>DS-CMP-017</span>
                    <h2 id="table-title">Tabela responsiva</h2>
                    <p>No computador aparece como tabela; no celular, os mesmos dados viram cartões legíveis.</p>
                </header>

                @php
                    $demoAccesses = [
                        ['time' => '16:01', 'name' => 'Camila Andrade', 'type' => 'Visitante', 'destination' => 'Bloco B — Apto 304', 'status' => 'Liberado', 'tone' => 'success'],
                        ['time' => '15:54', 'name' => 'Luciana Ferraz', 'type' => 'Prestador', 'destination' => 'Área comum — Manutenção', 'status' => 'Pendente', 'tone' => 'warning'],
                        ['time' => '15:41', 'name' => 'Bianca Moretti', 'type' => 'Visitante', 'destination' => 'Bloco A — Apto 208', 'status' => 'Negado', 'tone' => 'danger'],
                    ];
                @endphp

                <x-ui.responsive-table label="Acessos recentes de demonstração">
                    <x-slot:table>
                        <thead>
                            <tr>
                                <th scope="col">Horário</th>
                                <th scope="col">Pessoa</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Destino</th>
                                <th scope="col">Resultado</th>
                                <th scope="col"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($demoAccesses as $access)
                                <tr>
                                    <td class="numeric">{{ $access['time'] }}</td>
                                    <td><strong>{{ $access['name'] }}</strong></td>
                                    <td>{{ $access['type'] }}</td>
                                    <td>{{ $access['destination'] }}</td>
                                    <td><x-ui.badge :variant="$access['tone']">{{ $access['status'] }}</x-ui.badge></td>
                                    <td><x-ui.button variant="ghost" size="sm">Detalhes</x-ui.button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-slot:table>

                    <x-slot:cards>
                        <ul class="ui-mobile-records">
                            @foreach ($demoAccesses as $access)
                                <li>
                                    <div>
                                        <strong>{{ $access['name'] }}</strong>
                                        <small>{{ $access['type'] }} · {{ $access['destination'] }}</small>
                                    </div>
                                    <time>{{ $access['time'] }}</time>
                                    <x-ui.badge :variant="$access['tone']">{{ $access['status'] }}</x-ui.badge>
                                    <x-ui.button variant="ghost" size="sm">Detalhes</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    </x-slot:cards>
                </x-ui.responsive-table>
            </section>

            <section class="component-section" aria-labelledby="loading-title">
                <header>
                    <span>DS-CMP-014 e 015</span>
                    <h2 id="loading-title">Carregamento, progresso e erro</h2>
                    <p>O sistema sempre explica o que está acontecendo e qual ação está disponível.</p>
                </header>

                <div class="component-cards-grid">
                    <x-ui.card title="Processo em andamento" description="Estados para operações rápidas e mensuráveis">
                        <div class="component-loading-examples">
                            <x-ui.progress label="Consultando cadastro…" />
                            <x-ui.progress type="bar" label="Envio dos documentos" :value="68" />
                        </div>
                    </x-ui.card>

                    <x-ui.card title="Lista carregando" description="A estrutura permanece estável">
                        <x-ui.responsive-table label="Pessoas" state="loading" />
                    </x-ui.card>

                    <x-ui.card title="Falha recuperável" description="O erro não é apresentado como lista vazia">
                        <x-ui.responsive-table label="Veículos" state="error">
                            <x-slot:retry>
                                <x-ui.button variant="danger" size="sm">Tentar novamente</x-ui.button>
                            </x-slot:retry>
                        </x-ui.responsive-table>
                    </x-ui.card>
                </div>
            </section>

            <section class="component-section" aria-labelledby="overlays-title">
                <header>
                    <span>DS-CMP-024 e 025</span>
                    <h2 id="overlays-title">Janela e painel lateral</h2>
                    <p>Informações curtas abrem sem abandonar a tela atual.</p>
                </header>

                <x-ui.card>
                    <x-ui.action-group align="start">
                        <x-ui.modal
                            id="confirmModal"
                            title="Negar esta entrada?"
                            description="A tentativa será registrada e nenhum comando será enviado ao portão."
                            trigger-label="Abrir confirmação"
                            confirm-label="Confirmar negativa"
                            confirm-variant="danger"
                        >
                            <x-ui.alert variant="warning" title="Motivo obrigatório">
                                Na tela real, o operador deverá selecionar e justificar o motivo da negativa.
                            </x-ui.alert>
                        </x-ui.modal>

                        <x-ui.drawer
                            id="personDrawer"
                            title="Resumo da pessoa"
                            description="Consulta rápida sem perder a validação"
                            trigger-label="Abrir painel lateral"
                        >
                            <div class="component-person-summary">
                                <span class="avatar" aria-hidden="true">MV</span>
                                <div>
                                    <strong>Marcos Vinicius da Silva</strong>
                                    <small>Morador · Cadastro ativo</small>
                                </div>
                            </div>
                            <dl class="component-summary">
                                <div><dt>Documento</dt><dd>•••.•••.321-00</dd></div>
                                <div><dt>Imóvel</dt><dd>Bloco A — Apto 102</dd></div>
                                <div><dt>Validade</dt><dd>Acesso permanente</dd></div>
                            </dl>
                            <x-slot:footer>
                                <form method="dialog">
                                    <x-ui.button type="submit" variant="secondary">Fechar</x-ui.button>
                                </form>
                                <x-ui.button>Ver cadastro completo</x-ui.button>
                            </x-slot:footer>
                        </x-ui.drawer>
                    </x-ui.action-group>
                </x-ui.card>
            </section>

            <section class="component-section" aria-labelledby="advanced-fields-title">
                <header>
                    <span>DS-CMP-007, 008, 010 e 026 · Entrega 04</span>
                    <h2 id="advanced-fields-title">Formulários avançados</h2>
                    <p>Busca, período, arquivo e ajuda complementar com orientação clara.</p>
                </header>

                <div class="component-cards-grid component-cards-grid--two">
                    <x-ui.card title="Busca inteligente" description="Digite nome, documento ou imóvel">
                        <x-ui.autocomplete
                            id="demo-person-search"
                            label="Pessoa ou responsável"
                            placeholder="Comece a digitar um nome"
                            help="Use as setas do teclado e Enter para selecionar."
                            :options="[
                                ['value' => 1, 'label' => 'Marcos Vinicius da Silva', 'description' => 'CPF final 2100 · Bloco A — Apto 102'],
                                ['value' => 2, 'label' => 'Mariana da Silva', 'description' => 'CPF final 8842 · Bloco C — Apto 301'],
                                ['value' => 3, 'label' => 'Marcelo Souza', 'description' => 'Prestador · Vale Manutenção'],
                            ]"
                        />
                    </x-ui.card>

                    <x-ui.card title="Data e período" description="Valor interno sem ambiguidade">
                        <x-ui.date-range id="demo-validity" label="Vigência da autorização" start="2026-08-10" end="2026-08-15" allow-indefinite />
                    </x-ui.card>

                    <x-ui.card title="Upload e captura" description="Arquivo ainda não é enviado neste catálogo">
                        <x-ui.upload id="demo-document-upload" label="Selecionar documento" limit="Até 10 MB" />
                    </x-ui.card>

                    <x-ui.card title="Ajuda complementar" description="Funciona com mouse, teclado e toque">
                        <div class="component-row">
                            <span>Confiança da leitura</span>
                            <x-ui.tooltip text="Percentual informado pelo equipamento; não substitui a conferência do operador.">
                                <x-ui.button variant="secondary" size="sm" :icon-only="true" aria-label="Entender confiança da leitura"><x-slot:icon><x-icon name="info" /></x-slot:icon><span class="sr-only">Ajuda</span></x-ui.button>
                            </x-ui.tooltip>
                        </div>
                    </x-ui.card>
                </div>
            </section>

            <section class="component-section" aria-labelledby="operational-info-title">
                <header>
                    <span>DS-CMP-013, 018 e 019 · Entrega 04</span>
                    <h2 id="operational-info-title">Informações operacionais</h2>
                    <p>Confirmações, números e histórico sempre apresentam origem e contexto.</p>
                </header>

                <div class="component-toast-examples">
                    <x-ui.toast title="Cadastro salvo">As informações foram registradas. Nenhum comando foi enviado ao portão.</x-ui.toast>
                    <x-ui.toast variant="danger" title="Falha de comunicação">A autorização foi registrada, mas o equipamento não confirmou a abertura.</x-ui.toast>
                </div>

                <div class="component-metrics-grid">
                    <x-ui.metric label="Acessos hoje" value="184" period="10 de agosto" comparison="+12%" trend="up" icon="door" />
                    <x-ui.metric label="Contribuições" value="R$ 735,00" period="Caixa atual" comparison="23 registros" icon="wallet" />
                    <x-ui.metric label="Pendências" value="7" period="Agora" comparison="2 críticas" trend="down" icon="alert" state="Requer atenção" />
                </div>

                <x-ui.card title="Atividade recente" description="Eventos demonstrativos e rastreáveis">
                    <x-ui.activity-list>
                        <x-ui.activity-item title="Entrada liberada" description="Marcos Vinicius da Silva · Morador" datetime="10/08/2026 às 16:42" actor="João da Silva" location="Portaria Principal" status="Liberado" tone="success" icon="door" />
                        <x-ui.activity-item title="Cadastro atualizado" description="Telefone e veículo revisados" datetime="10/08/2026 às 16:30" actor="Ana Ferreira" status="Concluído" tone="info" icon="clipboard" />
                        <x-ui.activity-item title="Comando sem confirmação" description="Portão de serviço não respondeu" datetime="10/08/2026 às 16:18" actor="Controladora 02" location="Acesso de Serviço" status="Falha" tone="danger" icon="alert" />
                    </x-ui.activity-list>
                </x-ui.card>
            </section>

            <section class="component-section" aria-labelledby="domain-identification-title">
                <header>
                    <span>DS-CMP-027 a 032 · Entrega 05</span>
                    <h2 id="domain-identification-title">Identificação, vínculos e equipamentos</h2>
                    <p>Peças próprias da rotina da portaria, sem confundir cadastro com autorização.</p>
                </header>

                <x-ui.card>
                    <x-ui.access-type-selector selected="resident" />
                </x-ui.card>

                <div class="component-domain-grid">
                    <x-ui.person-summary
                        name="Marcos Vinicius da Silva"
                        initials="MV"
                        document="•••.•••.321-00"
                        type="Morador"
                        property="Bloco A — Apto 102"
                        responsible="Titular do imóvel"
                        status="Cadastro ativo"
                        validity="Acesso permanente"
                    >
                        <x-slot:actions><x-ui.button size="sm" variant="secondary">Ver cadastro completo</x-ui.button></x-slot:actions>
                    </x-ui.person-summary>

                    <x-ui.link-panel
                        property="Bloco A — Apto 102"
                        nature="Moradia"
                        responsibility="Titular"
                        period="Desde 15/05/2022"
                        :permissions="['Entrada 24 horas', 'Autorizar visitantes', 'Cadastrar veículos']"
                    >
                        <x-slot:actions><x-ui.button size="sm" variant="secondary">Consultar vínculo</x-ui.button></x-slot:actions>
                    </x-ui.link-panel>

                    <x-ui.vehicle-card plate="ABC1D23" model="Toyota Corolla 2022" color="Prata" owner="Marcos Vinicius da Silva" link="Titular" status="Cadastrado">
                        <x-slot:actions><x-ui.button size="sm" variant="secondary">Consultar veículo</x-ui.button></x-slot:actions>
                    </x-ui.vehicle-card>
                </div>

                <x-ui.lpr-comparison recognized="ABC1D23" registered="ABC1D23" :confidence="98" vehicle="Toyota Corolla · Prata" captured-at="10/08/2026 às 16:42:10">
                    <x-slot:actions><x-ui.button size="sm" variant="secondary">Alterar placa ou veículo</x-ui.button></x-slot:actions>
                </x-ui.lpr-comparison>

                <div class="component-sync-grid">
                    <x-ui.sync-status status="Sincronizado" equipment="Controladora 01" last-attempt="16:40:02" description="Credencial disponível no acesso principal." />
                    <x-ui.sync-status status="Atualização pendente" equipment="Controladora 02" last-attempt="16:41:18" description="Nova tentativa programada." tone="warning">
                        <x-slot:action><x-ui.button size="sm" variant="secondary">Tentar novamente</x-ui.button></x-slot:action>
                    </x-ui.sync-status>
                    <x-ui.sync-status status="Falha" equipment="Leitor Garagem" last-attempt="16:41:46" description="Equipamento sem comunicação." tone="danger">
                        <x-slot:action><x-ui.button size="sm" variant="danger">Ver ocorrência</x-ui.button></x-slot:action>
                    </x-ui.sync-status>
                </div>
            </section>

            <section class="component-section" aria-labelledby="domain-operation-title">
                <header>
                    <span>DS-CMP-033 a 036 · Entrega 05</span>
                    <h2 id="domain-operation-title">Decisão, contribuição, caixa e protocolo</h2>
                    <p>As ações críticas permanecem separadas e explicam seus efeitos.</p>
                </header>

                <x-ui.contribution amount="15,00" cashbox="Caixa Portaria Principal" />
                <x-ui.access-decision />

                <div class="component-domain-grid component-domain-grid--closing">
                    <x-ui.cash-summary
                        operator="João da Silva"
                        terminal="Portaria Principal"
                        opened-at="07:00"
                        opening-balance="R$ 100,00"
                        income="R$ 735,00"
                        expenses="R$ 35,00"
                        cancellations="R$ 15,00"
                        expected="R$ 785,00"
                        informed="R$ 785,00"
                        difference="R$ 0,00"
                    >
                        <x-slot:actions><x-ui.button size="sm" variant="secondary">Ver movimentações</x-ui.button></x-slot:actions>
                    </x-ui.cash-summary>

                    <x-ui.protocol number="SRA-20260810-004182" status="Acesso registrado" datetime="10/08/2026 às 16:42:15" />
                </div>
            </section>
        </main>
    </div>
</x-layouts.guest>

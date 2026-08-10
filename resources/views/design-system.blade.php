<x-layouts.guest title="Catálogo de componentes">
    <div class="component-catalog">
        <header class="component-catalog__header">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="Voltar ao Dashboard">
                <span class="brand__mark" aria-hidden="true">SDV</span>
                <span><strong>SDV Access</strong><small>Santa Rita</small></span>
            </a>
            <div>
                <x-ui.badge variant="info">P04 · Em desenvolvimento</x-ui.badge>
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
        </main>
    </div>
</x-layouts.guest>

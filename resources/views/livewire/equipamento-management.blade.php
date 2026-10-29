<div class="equipamento-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @if ($releSelecionado)
        <x-ui.card variant="default" class="equipamento-management-rele" title="Testar relé: {{ $releSelecionado->nome }}" description="Teste em bancada. Aciona somente o relé desta facial; não registra entrada nem saída de pessoa.">
            <x-ui.alert variant="warning" title="Teste em bancada">
                Confira se este é o terminal à sua frente e se não há cancela, catraca ou carga ligada ao relé. "Aceito pelo terminal" não comprova o acionamento físico: observe o clique do relé.
            </x-ui.alert>

            <dl class="equipamento-management-dados">
                <div><dt>Facial</dt><dd>{{ $releSelecionado->nome }}</dd></div>
                <div><dt>Fabricante e modelo</dt><dd>{{ $releSelecionado->fabricante }} {{ $releSelecionado->modelo }}</dd></div>
                <div><dt>Número de série</dt><dd class="equipamento-management-mono">{{ $releSelecionado->numero_serie ?? 'Não inventariado' }}</dd></div>
                <div><dt>Ponto de acesso</dt><dd>{{ $releSelecionado->pontoVigente()?->nome ?? 'Sem ponto' }}</dd></div>
                <div><dt>Direção</dt><dd>{{ $releSelecionado->direcao->value }}</dd></div>
                <div><dt>Endereço</dt><dd>{{ $releSelecionado->enderecoMascarado() }}</dd></div>
                <div><dt>Relé acionado</dt><dd>Porta {{ $releSelecionado->porta_rele }}</dd></div>
                <div><dt>Testes aceitos</dt><dd>{{ $releTestesAceitos }} de {{ $releTestesParaHomologar }} para homologar{{ $releSelecionado->rele_homologado_em ? ' · relé homologado em '.$releSelecionado->rele_homologado_em->format('d/m/Y H:i') : '' }}</dd></div>
            </dl>

            @if ($registrandoHomologacaoRele)
                <p class="equipamento-management-aviso">Testes aceitos neste ponto: {{ $releTestesAceitos }}. Registre somente se o clique/acionamento foi observado.</p>
            @elseif ($releSituacao)
                <p class="equipamento-management-resultado">
                    <strong>Resultado:</strong>
                    <x-ui.badge :variant="$releSituacao['variant']">{{ $releSituacao['rotulo'] }}</x-ui.badge>
                    {{ $releSituacao['detalhe'] }}
                </p>
                <footer class="equipamento-management-acoes">
                    <x-ui.button variant="secondary" wire:click="cancelarTesteRele">Fechar</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="repetirTesteRele">Testar de novo</x-ui.button>
                    @if (! $releSelecionado->rele_homologado_em && $releTestesAceitos >= $releTestesParaHomologar)
                        <x-ui.button variant="primary" wire:click="iniciarHomologacaoRele">Registrar relé homologado</x-ui.button>
                    @endif
                </footer>
            @elseif ($releBloqueios !== [])
                <p class="equipamento-management-aviso">{{ reset($releBloqueios) }}</p>
                <footer class="equipamento-management-acoes">
                    <x-ui.button variant="secondary" wire:click="cancelarTesteRele">Fechar</x-ui.button>
                </footer>
            @else
                <form wire:submit="testarRele" class="equipamento-management-form">
                    <x-ui.field id="rele-motivo" label="Motivo" wire:model="motivoLiberacao" placeholder="Ex.: homologação do relé da facial em bancada" :error="$errors->first('motivoLiberacao')" required />
                    <x-ui.checkbox id="rele-ciente" label="Confirmo que é um teste em bancada desta facial, sem cancela ou catraca ligada" description="O comando é enviado uma única vez para a porta {{ $releSelecionado->porta_rele }} deste terminal." wire:model="cienteLiberacao" />
                    @error('cienteLiberacao')<small class="ui-field__message ui-field__message--error">{{ $message }}</small>@enderror
                    <footer>
                        <x-ui.button type="button" variant="secondary" wire:click="cancelarTesteRele">Cancelar</x-ui.button>
                        <x-ui.button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="testarRele">Confirmar teste do relé</x-ui.button>
                    </footer>
                </form>
            @endif

            @if ($registrandoHomologacaoRele)
                <form wire:submit="registrarHomologacaoRele" class="equipamento-management-form">
                    <x-ui.checkbox id="rele-observado" label="Observei o clique/acionamento do relé nos testes" wire:model="acionamentoObservado" />
                    <x-ui.field id="rele-observacao" label="Observação" wire:model="observacaoRele" placeholder="Ex.: clique ouvido nos dois testes" required />
                    <footer>
                        <x-ui.button type="button" variant="secondary" wire:click="$set('registrandoHomologacaoRele', false)">Cancelar</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Registrar relé homologado</x-ui.button>
                    </footer>
                </form>
            @endif
        </x-ui.card>
    @endif

    @if ($mode === 'list')
        <x-ui.card title="Terminais cadastrados" description="Somente equipamentos desta implantação.">
            <x-slot:headerAction>
                <x-ui.button variant="primary" size="sm" wire:click="novoEquipamento">Cadastrar equipamento</x-ui.button>
            </x-slot:headerAction>

            @if ($equipamentos->isEmpty())
                <x-ui.empty-state title="Nenhum equipamento cadastrado" description="Cadastre a facial de homologação para testar a conexão." />
            @else
                <ul class="equipamento-management-list">
                    @foreach ($equipamentos as $item)
                        <li>
                            <div class="equipamento-management-list__info">
                                <strong>{{ $item->nome }}</strong>
                                <small>{{ $item->fabricante }} {{ $item->modelo }} · {{ $item->vinculoVigente?->pontoAcesso?->nome ?? 'sem ponto' }}</small>
                            </div>
                            <div class="equipamento-management-list__estado">
                                <x-ui.badge :variant="$item->estado_saude->value === 'conectado' ? 'success' : 'neutral'">
                                    {{ $item->estado_saude->value === 'conectado' ? 'Online' : 'Offline ou não testado' }}
                                </x-ui.badge>
                                <x-ui.badge variant="info">{{ $item->status->rotulo() }}</x-ui.badge>
                                <x-ui.button variant="secondary" size="sm" wire:click="selecionar('{{ $item->id }}')">Abrir</x-ui.button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        @if ($podeLiberar)
            <x-ui.card title="Teste individual de relés" description="Escolha a facial e acione somente o relé dela, em bancada. O botão fica bloqueado enquanto algum requisito de segurança não for atendido.">
                @if ($faciaisRele === [])
                    <p class="equipamento-management-aviso">Nenhuma facial cadastrada.</p>
                @else
                    <ul class="equipamento-management-list">
                        @foreach ($faciaisRele as $linha)
                            @php $facial = $linha['equipamento']; @endphp
                            <li>
                                <div class="equipamento-management-list__info">
                                    <strong>{{ $facial->nome }}</strong>
                                    <small>
                                        {{ $facial->vinculoVigente?->pontoAcesso?->nome ?? 'sem ponto' }} · {{ $facial->direcao->value }} · porta {{ $facial->porta_rele }} ·
                                        última comunicação {{ $facial->ultima_comunicacao_at?->format('d/m/Y H:i') ?? 'nunca' }}
                                    </small>
                                    @if ($linha['bloqueios'] !== [])
                                        <small class="equipamento-management-bloqueio">{{ reset($linha['bloqueios']) }}</small>
                                    @endif
                                </div>
                                <div class="equipamento-management-list__estado">
                                    <x-ui.badge :variant="$facial->estado_saude->value === 'conectado' ? 'success' : 'neutral'">{{ $facial->estado_saude->value === 'conectado' ? 'Online' : 'Offline' }}</x-ui.badge>
                                    <x-ui.badge variant="info">{{ $facial->status->rotulo() }}</x-ui.badge>
                                    <x-ui.badge :variant="$facial->rele_homologado_em ? 'success' : 'warning'">{{ $facial->rele_homologado_em ? 'Relé homologado' : 'Relé em homologação ('.$linha['testes_aceitos'].' aceitos)' }}</x-ui.badge>
                                    <x-ui.button variant="danger" size="sm" wire:click="prepararTesteRele('{{ $facial->id }}')" :disabled="$linha['bloqueios'] !== []" title="{{ $linha['bloqueios'] === [] ? 'Testar o relé desta facial' : reset($linha['bloqueios']) }}">Testar relé</x-ui.button>
                                    @if (! $facial->rele_homologado_em && $linha['testes_aceitos'] >= $releTestesParaHomologar)
                                        <x-ui.button variant="primary" size="sm" wire:click="abrirHomologacaoRele('{{ $facial->id }}')">Registrar relé homologado</x-ui.button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif
    @elseif ($mode === 'form')
        <x-ui.card title="Novo terminal" description="O terminal é cadastrado em homologação e vinculado à bancada de testes, sem cancela ou catraca.">
            <form wire:submit="salvarEquipamento" class="equipamento-management-form" autocomplete="off">
                <div class="equipamento-management-grid">
                    <x-ui.field id="eq-nome" label="Nome" wire:model="form.nome" :error="$errors->first('form.nome')" required />
                    <x-ui.field id="eq-fabricante" label="Fabricante" wire:model="form.fabricante" :error="$errors->first('form.fabricante')" required />
                    <x-ui.field id="eq-modelo" label="Modelo" wire:model="form.modelo" :error="$errors->first('form.modelo')" required />
                    <x-ui.field id="eq-serie" label="Número de série" wire:model="form.numero_serie" help="Preenchido pelo teste de conexão se ficar vazio." :error="$errors->first('form.numero_serie')" />
                    <x-ui.field id="eq-firmware" label="Firmware" wire:model="form.firmware_versao" help="Preenchido pelo teste de conexão se ficar vazio." :error="$errors->first('form.firmware_versao')" />
                    <x-ui.field id="eq-ip" label="Endereço IP" wire:model="form.endereco_rede" placeholder="192.168.x.x" help="IP fixo ou reservado na rede local." :error="$errors->first('form.endereco_rede')" required />
                    <x-ui.select id="eq-esquema" label="Protocolo HTTP" wire:model="form.esquema" :error="$errors->first('form.esquema')">
                        <option value="https">HTTPS</option>
                        <option value="http">HTTP</option>
                    </x-ui.select>
                    <x-ui.field id="eq-porta" label="Porta" type="number" wire:model="form.porta_rede" help="Vazio usa 443 (HTTPS) ou 80 (HTTP)." :error="$errors->first('form.porta_rede')" />
                    <x-ui.field id="eq-protocolo" label="Protocolo de integração" wire:model="form.protocolo" :error="$errors->first('form.protocolo')" required />
                    <x-ui.select id="eq-adaptador" label="Adaptador" wire:model="form.adaptador" :error="$errors->first('form.adaptador')">
                        @foreach ($adaptadoresDisponiveis as $codigo)
                            <option value="{{ $codigo }}">{{ $codigo }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select id="eq-direcao" label="Direção" wire:model="form.direcao" :error="$errors->first('form.direcao')">
                        <option value="entrada">Entrada</option>
                        <option value="saida">Saída</option>
                        <option value="bidirecional">Bidirecional</option>
                    </x-ui.select>
                    <x-ui.select id="eq-ponto" label="Ponto de acesso" wire:model="form.ponto_id">
                        <option value="">Bancada de testes (criar se não existir)</option>
                        @foreach ($pontos as $ponto)
                            <option value="{{ $ponto->id }}">{{ $ponto->nome }} ({{ $ponto->tipo->value }})</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field id="eq-timeout" label="Tempo limite (s)" type="number" wire:model="form.timeout_segundos" :error="$errors->first('form.timeout_segundos')" required />
                </div>

                <x-ui.checkbox id="eq-rs485" label="Usa módulo seguro RS-485" description="O relé fica no módulo seguro, fora do terminal." wire:model="form.modulo_seguro_rs485" />

                <fieldset class="equipamento-management-segredo">
                    <legend>Credencial técnica</legend>
                    <p>A senha é enviada somente ao servidor, gravada cifrada e nunca é exibida de novo.</p>
                    <div class="equipamento-management-grid">
                        <x-ui.field id="eq-usuario" label="Usuário técnico" wire:model="usuarioTecnico" autocomplete="off" :error="$errors->first('usuarioTecnico')" required />
                        <x-ui.field id="eq-senha" label="Senha técnica" type="password" wire:model="senhaTecnica" autocomplete="new-password" :error="$errors->first('senhaTecnica')" />
                    </div>
                </fieldset>

                <footer>
                    <x-ui.button type="button" variant="secondary" wire:click="voltar">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="salvarEquipamento">Salvar</x-ui.button>
                </footer>
            </form>
        </x-ui.card>
    @elseif ($mode === 'detail' && $equipamento)
        <div class="equipamento-management-detail">
            <x-ui.card :title="$equipamento->nome" :description="$equipamento->fabricante.' '.$equipamento->modelo">
                <x-slot:headerAction>
                    <x-ui.button variant="ghost" size="sm" wire:click="voltar">Voltar</x-ui.button>
                </x-slot:headerAction>

                <div class="equipamento-management-estado">
                    <x-ui.badge :variant="$detalhe['online'] ? 'success' : 'neutral'">{{ $detalhe['online'] ? 'Online' : 'Offline ou não testado' }}</x-ui.badge>
                    <x-ui.badge variant="info">{{ $equipamento->status->rotulo() }}</x-ui.badge>
                    @if ($detalhe['saude']->value === 'credencial_invalida')
                        <x-ui.badge variant="danger">Credencial recusada</x-ui.badge>
                    @endif
                </div>

                <dl class="equipamento-management-dados">
                    <div><dt>Ponto</dt><dd>{{ $detalhe['ponto']?->nome ?? 'Sem ponto' }}</dd></div>
                    <div><dt>Direção</dt><dd>{{ $equipamento->direcao->value }}</dd></div>
                    <div><dt>Endereço</dt><dd>{{ strtoupper($equipamento->esquema) }} {{ $equipamento->enderecoMascarado() }}{{ $equipamento->porta_rede ? ':'.$equipamento->porta_rede : '' }}</dd></div>
                    <div><dt>Relé</dt><dd>Porta {{ $equipamento->porta_rele }}{{ $equipamento->rele_homologado_em ? ' · homologado em '.$equipamento->rele_homologado_em->format('d/m/Y H:i') : ' · não homologado' }}</dd></div>
                    <div><dt>Integração</dt><dd>{{ $equipamento->protocolo }} · {{ $equipamento->adaptador }}</dd></div>
                    <div><dt>Firmware</dt><dd>{{ $equipamento->firmware_versao ?? 'Não inventariado' }}</dd></div>
                    <div><dt>Número de série</dt><dd>{{ $equipamento->numero_serie ?? 'Não inventariado' }}</dd></div>
                    <div><dt>Módulo seguro RS-485</dt><dd>{{ $equipamento->modulo_seguro_rs485 ? 'Sim' : 'Não' }}</dd></div>
                    <div><dt>Credencial técnica</dt><dd>{{ $detalhe['credencialDefinida'] ? 'Cadastrada (protegida)' : 'Não cadastrada' }}</dd></div>
                    <div><dt>Certificado HTTPS</dt><dd>{{ $equipamento->tls_pin_sha256 ? 'Confiado (chave fixada)' : ($equipamento->esquema === 'https' ? 'Não confiado' : 'Sem HTTPS') }}</dd></div>
                    <div><dt>Última comunicação</dt><dd>{{ $equipamento->ultima_comunicacao_at?->format('d/m/Y H:i:s') ?? 'Nunca' }}</dd></div>
                    <div><dt>Última falha</dt><dd>{{ $equipamento->ultima_falha_at?->format('d/m/Y H:i:s') ?? 'Nenhuma' }}{{ $equipamento->ultimo_erro_sanitizado ? ' · '.$equipamento->ultimo_erro_sanitizado : '' }}</dd></div>
                    <div><dt>Cadastro</dt><dd>{{ $equipamento->created_at?->format('d/m/Y H:i') }} · atualizado {{ $equipamento->updated_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>

                @if ($equipamento->estado_saude->value === 'desconhecido' && $equipamento->status->value !== 'inativo')
                    <x-ui.alert variant="warning" title="Conexão não testada">
                        Execute "Testar conexão" antes de usar este terminal.{{ $equipamento->esquema === 'https' && ! $equipamento->tls_pin_sha256 ? ' Em HTTPS, leia e confirme o certificado apresentado pelo terminal.' : '' }}
                    </x-ui.alert>
                @endif

                <div class="equipamento-management-acoes">
                    @if ($equipamento->status->value !== 'inativo' && ! $editando)
                        <x-ui.button variant="secondary" wire:click="iniciarEdicao">Editar equipamento</x-ui.button>
                    @endif
                    <x-ui.button variant="primary" wire:click="testarConexao" wire:loading.attr="disabled" wire:target="testarConexao">Testar conexão</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="consultarCapacidades" wire:loading.attr="disabled" wire:target="consultarCapacidades">Consultar capacidades</x-ui.button>
                    @if ($equipamento->esquema === 'https')
                        <x-ui.button variant="secondary" wire:click="lerCertificado" wire:loading.attr="disabled" wire:target="lerCertificado">Ler certificado</x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" wire:click="iniciarTrocaSenha">Trocar credencial</x-ui.button>
                    @if (! in_array($equipamento->status->value, ['em_homologacao', 'inativo'], true))
                        <x-ui.button variant="ghost" wire:click="colocarEmHomologacao">Colocar em homologação</x-ui.button>
                    @endif
                </div>

                @if ($detalhe['ultimaOperacao'])
                    <p class="equipamento-management-resultado">
                        <strong>Resultado mais recente:</strong>
                        {{ str_replace('_', ' ', $detalhe['ultimaOperacao']['operacao']->operacao->value) }} ·
                        <x-ui.badge :variant="$detalhe['ultimaOperacao']['situacao']['variant']">{{ $detalhe['ultimaOperacao']['situacao']['rotulo'] }}</x-ui.badge>
                        {{ $detalhe['ultimaOperacao']['situacao']['detalhe'] }}
                        <small>({{ $detalhe['ultimaOperacao']['operacao']->created_at->format('d/m/Y H:i:s') }})</small>
                    </p>
                @endif
            </x-ui.card>

            @if ($editando)
                <x-ui.card title="Editar equipamento" description="Altera este mesmo terminal, preservando identificador, vínculos anteriores, operações, eventos e histórico. A credencial técnica não é alterada aqui.">
                    @if ($previaEdicao)
                        <div class="equipamento-management-previa">
                            @if ($previaAfetaConexao)
                                <x-ui.alert variant="warning" title="Confira antes de salvar">
                                    A comunicação com este equipamento poderá ficar indisponível até um novo teste de conexão. Nenhum histórico será apagado.
                                </x-ui.alert>
                            @else
                                <x-ui.alert variant="info" title="Confira antes de salvar">
                                    O terminal volta para homologação. Nenhum histórico será apagado.
                                </x-ui.alert>
                            @endif

                            <table class="equipamento-management-comparacao">
                                <caption>Alterações deste equipamento</caption>
                                <thead>
                                    <tr><th scope="col">Campo</th><th scope="col">Valor atual</th><th scope="col">Novo valor</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($previaEdicao as $linha)
                                        <tr>
                                            <th scope="row">{{ $linha['rotulo'] }}</th>
                                            <td>{{ $linha['atual'] }}</td>
                                            <td><strong>{{ $linha['novo'] }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <footer class="equipamento-management-acoes">
                                <x-ui.button variant="secondary" wire:click="voltarParaEdicao">Voltar à edição</x-ui.button>
                                <x-ui.button variant="primary" wire:click="confirmarEdicao" wire:loading.attr="disabled" wire:target="confirmarEdicao">Confirmar alterações</x-ui.button>
                            </footer>
                        </div>
                    @else
                        <form wire:submit="revisarEdicao" class="equipamento-management-form" autocomplete="off">
                            <div class="equipamento-management-grid">
                                <x-ui.field id="ed-nome" label="Nome" wire:model="edicao.nome" :error="$errors->first('edicao.nome')" required />
                                <x-ui.field id="ed-ip" label="Endereço IP" wire:model="edicao.endereco_rede" help="IP fixo ou reservado na rede local." :error="$errors->first('edicao.endereco_rede')" required />
                                <x-ui.select id="ed-esquema" label="Protocolo HTTP" wire:model="edicao.esquema" :error="$errors->first('edicao.esquema')">
                                    <option value="https">HTTPS</option>
                                    <option value="http">HTTP</option>
                                </x-ui.select>
                                <x-ui.field id="ed-porta" label="Porta" type="number" wire:model="edicao.porta_rede" help="Vazio usa 443 (HTTPS) ou 80 (HTTP)." :error="$errors->first('edicao.porta_rede')" />
                                <x-ui.field id="ed-timeout" label="Tempo limite (s)" type="number" wire:model="edicao.timeout_segundos" :error="$errors->first('edicao.timeout_segundos')" required />
                                <x-ui.field id="ed-protocolo" label="Protocolo de integração" wire:model="edicao.protocolo" :error="$errors->first('edicao.protocolo')" required />
                                <x-ui.select id="ed-adaptador" label="Adaptador" wire:model="edicao.adaptador" :error="$errors->first('edicao.adaptador')">
                                    @foreach (array_unique([$equipamento->adaptador, ...$adaptadoresDisponiveis]) as $codigo)
                                        <option value="{{ $codigo }}">{{ $codigo }}</option>
                                    @endforeach
                                </x-ui.select>
                                <x-ui.select id="ed-direcao" label="Direção" wire:model="edicao.direcao" :error="$errors->first('edicao.direcao')">
                                    <option value="entrada">Entrada</option>
                                    <option value="saida">Saída</option>
                                    <option value="bidirecional">Bidirecional</option>
                                </x-ui.select>
                                <x-ui.select id="ed-ponto" label="Ponto de acesso" wire:model="edicao.ponto_id" help="Trocar o ponto encerra o vínculo atual e cria outro; o anterior fica no histórico.">
                                    @unless ($detalhe['ponto'])<option value="">Escolha o ponto</option>@endunless
                                    @foreach ($pontos as $ponto)
                                        <option value="{{ $ponto->id }}">{{ $ponto->nome }} ({{ $ponto->tipo->value }})</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <x-ui.checkbox id="ed-rs485" label="Usa módulo seguro RS-485" description="O relé fica no módulo seguro, fora do terminal." wire:model="edicao.modulo_seguro_rs485" />

                            <p class="equipamento-management-aviso">A senha técnica não é exibida nem pedida aqui. Para alterá-la, use "Trocar credencial". Salvar não testa a conexão, não aciona o relé e não sincroniza pessoas.</p>

                            <footer>
                                <x-ui.button type="button" variant="secondary" wire:click="cancelarEdicao">Cancelar</x-ui.button>
                                <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="revisarEdicao">Revisar e salvar</x-ui.button>
                            </footer>
                        </form>
                    @endif
                </x-ui.card>
            @endif

            @if ($certificadoLido)
                <x-ui.card title="Certificado apresentado pelo terminal" description="Confira antes de confiar. Depois disso, só este certificado será aceito para este terminal.">
                    <dl class="equipamento-management-dados">
                        <div><dt>Sujeito</dt><dd>{{ $certificadoLido['sujeito'] }}</dd></div>
                        <div><dt>Emissor</dt><dd>{{ $certificadoLido['emissor'] }}{{ $certificadoLido['autoassinado'] ? ' (autoassinado)' : '' }}</dd></div>
                        <div><dt>Válido até</dt><dd>{{ \Illuminate\Support\Carbon::parse($certificadoLido['valido_ate'])->format('d/m/Y') }}</dd></div>
                        <div><dt>Impressão SHA-256</dt><dd class="equipamento-management-mono">{{ $certificadoLido['impressao_sha256'] }}</dd></div>
                    </dl>
                    <footer class="equipamento-management-acoes">
                        <x-ui.button variant="secondary" wire:click="$set('certificadoLido', null)">Descartar</x-ui.button>
                        <x-ui.button variant="primary" wire:click="confiarCertificado">Confiar neste certificado</x-ui.button>
                    </footer>
                </x-ui.card>
            @endif

            @if ($trocandoSenha)
                <x-ui.card title="Trocar credencial técnica" description="A credencial atual é substituída; a senha nunca é exibida.">
                    <form wire:submit="salvarSenha" class="equipamento-management-form" autocomplete="off">
                        <div class="equipamento-management-grid">
                            <x-ui.field id="troca-usuario" label="Usuário técnico" wire:model="usuarioTecnico" autocomplete="off" required />
                            <x-ui.field id="troca-senha" label="Nova senha técnica" type="password" wire:model="senhaTecnica" autocomplete="new-password" required />
                        </div>
                        <footer>
                            <x-ui.button type="button" variant="secondary" wire:click="$set('trocandoSenha', false)">Cancelar</x-ui.button>
                            <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="salvarSenha">Salvar credencial</x-ui.button>
                        </footer>
                    </form>
                </x-ui.card>
            @endif

            <x-ui.card title="Imagem estática" description="Capturada pelo SDV, sem streaming. Guardada por poucos minutos e nunca usada como biometria.">
                <x-slot:headerAction>
                    <x-ui.button variant="secondary" size="sm" wire:click="atualizarImagem" wire:loading.attr="disabled" wire:target="atualizarImagem" :disabled="! $detalhe['suportaImagem']">Atualizar imagem</x-ui.button>
                </x-slot:headerAction>

                @if (! $detalhe['suportaImagem'])
                    <p class="equipamento-management-aviso">Captura não homologada para este terminal. Teste a conexão e consulte as capacidades.</p>
                @elseif ($detalhe['imagemDisponivel'])
                    <figure class="equipamento-management-imagem">
                        <img src="{{ route('equipment.image', $equipamento) }}?v={{ $ultimaCaptura['capturada_em'] ?? now()->timestamp }}" alt="Imagem estática da câmera do terminal">
                        <figcaption>
                            Capturada em {{ isset($ultimaCaptura['capturada_em']) ? \Illuminate\Support\Carbon::parse($ultimaCaptura['capturada_em'])->timezone(config('app.timezone'))->format('d/m/Y H:i:s') : 'captura recente' }}
                        </figcaption>
                    </figure>
                @else
                    <p class="equipamento-management-aviso">Imagem indisponível. {{ $ultimaCaptura['mensagem'] ?? 'Clique em Atualizar imagem.' }}</p>
                @endif
            </x-ui.card>

            @if ($podeLiberar)
                <x-ui.card title="Teste do relé" description="Aciona somente o relé desta facial, em bancada, depois de uma confirmação com os dados do terminal.">
                    <div class="equipamento-management-acoes">
                        <x-ui.button variant="danger" wire:click="prepararTesteRele('{{ $equipamento->id }}')">Testar relé</x-ui.button>
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card title="Capacidades detectadas" description="Matriz de homologação do firmware verificado. Só executa o que está homologado ou, na bancada, em homologação.">
                <x-slot:headerAction>
                    <x-ui.button variant="secondary" size="sm" wire:click="consultarCapacidades" wire:loading.attr="disabled" wire:target="consultarCapacidades">Consultar capacidades</x-ui.button>
                </x-slot:headerAction>
                @if ($detalhe['capacidades']->isEmpty())
                    <p class="equipamento-management-aviso">Ainda não consultadas.</p>
                @else
                    <ul class="equipamento-management-capacidades">
                        @foreach ($detalhe['capacidades'] as $capacidade)
                            <li>
                                @php
                                    $estado = $capacidade->estado_homologacao;
                                    $variante = match ($estado?->value) {
                                        'homologada' => 'success',
                                        'em_homologacao' => 'info',
                                        'detectada' => 'warning',
                                        'bloqueada' => 'danger',
                                        default => 'neutral',
                                    };
                                @endphp
                                <x-ui.badge :variant="$variante">{{ $estado?->rotulo() ?? ($capacidade->suportada ? 'Liberada' : 'Não verificada') }}</x-ui.badge>
                                <span>{{ str_replace('_', ' ', $capacidade->capacidade) }}</span>
                                @if (! $capacidade->suportada && $capacidade->motivo_ausencia)
                                    <small>{{ $capacidade->motivo_ausencia }}</small>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Histórico auditável" description="Operações registradas para este equipamento, sem dados sensíveis.">
                @if ($detalhe['historico']->isEmpty())
                    <p class="equipamento-management-aviso">Sem registros.</p>
                @else
                    <ul class="equipamento-management-historico">
                        @foreach ($detalhe['historico'] as $evento)
                            <li>
                                <time>{{ $evento->occurred_at?->format('d/m/Y H:i:s') }}</time>
                                <strong>{{ str_replace('_', ' ', $evento->action) }}</strong>
                                <x-ui.badge :variant="match ($evento->result) { 'sucesso' => 'success', 'aceito' => 'info', 'falha' => 'danger', 'desconhecido' => 'warning', default => 'neutral' }">{{ $evento->result }}</x-ui.badge>
                                <span>{{ $evento->actor_name }}{{ $evento->reason_code ? ' · '.$evento->reason_code : '' }}{{ $evento->justification ? ' · '.$evento->justification : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>

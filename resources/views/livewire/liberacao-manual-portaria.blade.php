<div class="liberacao-manual">
    <x-ui.card title="Liberação manual" description="Acione o relé de um ponto para liberar uma entrada ou saída. Cada liberação gera evento de acesso e auditoria.">
        @if (! $podeLiberar)
            <p class="equipamento-management-aviso">Você não tem a permissão de liberação manual.</p>
        @elseif (! $habilitada)
            <x-ui.alert variant="warning" title="Liberação manual desligada">
                A liberação operacional está desligada nesta instalação até a homologação e a aprovação formal.
            </x-ui.alert>
        @elseif (! $posto)
            <x-ui.alert variant="info" title="Selecione o seu posto">
                Escolha o posto em que você está trabalhando para ver os relés disponíveis.
            </x-ui.alert>
            <footer class="equipamento-management-acoes">
                <x-ui.button variant="primary" :href="route('posto.selecionar', ['voltar' => '/portaria'])">Selecionar posto</x-ui.button>
            </footer>
        @elseif ($evento)
            @php
                [$rotulo, $variante, $texto] = match ($evento->resultado_tecnico) {
                    'aceita' => ['Aceita pelo terminal', 'success', 'O terminal aceitou o comando. Isso não confirma a passagem física: não há sensor no ponto.'],
                    'confirmada' => ['Confirmada', 'success', 'O equipamento confirmou a execução do comando.'],
                    'recusada' => ['Recusada', 'danger', 'O terminal recusou o comando. A passagem não foi liberada pelo equipamento.'],
                    'desconhecida' => ['Confirmação desconhecida', 'warning', 'Sem resposta conclusiva do terminal. Não repita às cegas: confira o ponto.'],
                    'expirada' => ['Expirada', 'warning', 'O comando expirou antes do envio.'],
                    'cancelada' => ['Cancelada', 'neutral', 'O comando foi cancelado.'],
                    'solicitada' => ['Solicitada', 'neutral', 'Aguardando envio ao terminal.'],
                    'enviada' => ['Enviada', 'neutral', 'Enviada ao terminal, aguardando resposta.'],
                    default => ['Falha', 'danger', $evento->operacaoIntegracao?->erro_sanitizado ?? 'Falha técnica no envio.'],
                };
            @endphp
            <div class="liberacao-manual__resultado">
                <p>
                    <x-ui.badge :variant="$variante">{{ $rotulo }}</x-ui.badge>
                    @if ($evento->excepcional)<x-ui.badge variant="danger">Excepcional</x-ui.badge>@endif
                    @if ($evento->fora_do_posto)<x-ui.badge variant="warning">Fora do posto</x-ui.badge>@endif
                    {{ $texto }}
                </p>
                <dl class="equipamento-management-dados">
                    <div><dt>Protocolo</dt><dd class="equipamento-management-mono">{{ $evento->protocol }}</dd></div>
                    <div><dt>Equipamento</dt><dd>{{ $evento->equipamento?->nome }}</dd></div>
                    <div><dt>Ponto</dt><dd>{{ $evento->pontoAcessoRegistro?->nome ?? $evento->ponto_acesso }}</dd></div>
                    <div><dt>Direção</dt><dd>{{ $evento->tipo === 'saida' ? 'Saída' : 'Entrada' }}</dd></div>
                </dl>
                <footer class="equipamento-management-acoes">
                    <x-ui.button variant="primary" wire:click="novaLiberacao">Nova liberação</x-ui.button>
                </footer>
            </div>
        @else
            <p class="equipamento-management-resultado">
                <strong>Posto ativo:</strong> {{ $posto->nome }}
                <a href="{{ route('posto.selecionar', ['voltar' => '/portaria']) }}">Trocar posto</a>
            </p>

            @if (! $pessoa)
                <x-ui.field id="lib-busca" label="Pessoa" wire:model.live.debounce.400ms="busca" placeholder="Nome ou documento (mínimo 3 caracteres)" :error="$errors->first('pessoaId')" />
                @if ($resultadosBusca->isNotEmpty())
                    <ul class="liberacao-manual__busca">
                        @foreach ($resultadosBusca as $resultado)
                            <li><button type="button" class="ui-button ui-button--ghost ui-button--sm" wire:click="selecionarPessoa('{{ $resultado->id }}')"><span class="ui-button__content">{{ $resultado->nomeExibicao() }}</span></button></li>
                        @endforeach
                    </ul>
                @endif
            @else
                <section class="liberacao-manual__pessoa" aria-label="Pessoa selecionada">
                    <span class="liberacao-manual__avatar" aria-hidden="true">{{ $pessoa['iniciais'] }}</span>
                    <dl class="equipamento-management-dados">
                        <div><dt>Nome</dt><dd>{{ $pessoa['nome'] }}</dd></div>
                        <div><dt>Documento</dt><dd>{{ $pessoa['documento'] }}</dd></div>
                        <div><dt>Perfil</dt><dd>{{ $pessoa['perfil'] }}</dd></div>
                        <div><dt>Imóvel e vínculo</dt><dd>{{ $pessoa['imovel'] }}</dd></div>
                        <div><dt>Autorização</dt><dd>{{ $pessoa['autorizacao'] }}</dd></div>
                        <div><dt>Validade</dt><dd>{{ $pessoa['validade'] }}</dd></div>
                    </dl>
                    @if ($pessoa['restricoes'] !== [])
                        <x-ui.alert variant="danger" title="Restrições">
                            {{ implode(' ', $pessoa['restricoes']) }} A liberação normal fica bloqueada.
                        </x-ui.alert>
                    @endif
                    <x-ui.button variant="ghost" size="sm" wire:click="$set('pessoaId', null)">Trocar pessoa</x-ui.button>
                </section>
                @if ($pessoa['arquivos']['pre_registration_id'])
                    <x-protected-file-review :document-link="$pessoa['arquivos']['document']" :selfie-link="$pessoa['arquivos']['selfie']" :id="'liberacao-'.$pessoa['arquivos']['pre_registration_id']" title="Foto e documento" />
                @else
                    <p class="equipamento-management-aviso">Sem foto protegida disponível para esta pessoa.</p>
                @endif
            @endif

            @if ($podeExcepcional)
                <div class="liberacao-manual__excepcional">
                    <x-ui.checkbox id="lib-excepcional" label="Liberação excepcional — prosseguir sem autorização regular confirmada" description="Aplica o motivo padronizado e marca o evento para revisão." wire:model.live="excepcional" />
                </div>
            @endif
            @error('excepcional')<small class="ui-field__message ui-field__message--error">{{ $message }}</small>@enderror

            <div class="equipamento-management-grid">
                <x-ui.select id="lib-direcao" label="Direção" wire:model.live="direcao" :error="$errors->first('direcao')">
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                </x-ui.select>
                <x-ui.select id="lib-equipamento" label="Ponto e equipamento" wire:model.live="equipamentoId" :error="$errors->first('equipamentoId')">
                    <option value="">{{ $preferenciais === [] && $outros === [] ? 'Nenhum equipamento disponível' : 'Escolha o equipamento' }}</option>
                    @if ($preferenciais !== [])
                        <optgroup label="Do posto {{ $posto->nome }}">
                            @foreach ($preferenciais as $item)
                                <option value="{{ $item['equipamento']->id }}">{{ $item['equipamento']->nome }} — {{ $item['ponto']->nome }} · {{ $item['ponto']->direcao_suportada->value }} · {{ $item['equipamento']->estado_saude->value === 'conectado' ? 'online' : 'offline' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if ($mostrarOutros && $outros !== [])
                        <optgroup label="Outros pontos de acesso">
                            @foreach ($outros as $item)
                                <option value="{{ $item['equipamento']->id }}">{{ $item['equipamento']->nome }} — {{ $item['ponto']->nome }} · {{ $item['ponto']->direcao_suportada->value }} · {{ $item['equipamento']->estado_saude->value === 'conectado' ? 'online' : 'offline' }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </x-ui.select>
            </div>

            @if ($podeOutros && ! $posto->principal())
                <div class="liberacao-manual__modo">
                    <x-ui.button variant="secondary" size="sm" wire:click="$toggle('mostrarOutros')">{{ $mostrarOutros ? 'Ocultar outros pontos de acesso' : 'Outros pontos de acesso ('.count($outros).')' }}</x-ui.button>
                </div>
            @endif

            @if ($escolhido)
                <p class="equipamento-management-resultado">
                    <strong>Será acionado:</strong> {{ $escolhido['equipamento']->nome }}, no ponto {{ $escolhido['ponto']->nome }}
                    <x-ui.badge :variant="$escolhido['equipamento']->estado_saude->value === 'conectado' ? 'success' : 'neutral'">{{ $escolhido['equipamento']->estado_saude->value === 'conectado' ? 'Online' : 'Offline' }}</x-ui.badge>
                    @unless ($escolhido['do_posto'])<x-ui.badge variant="warning">Fora do posto</x-ui.badge>@endunless
                </p>
            @elseif ($preferenciais === [] && ! $mostrarOutros)
                <div class="equipamento-management-aviso">
                    <p>Nenhum equipamento do posto está pronto para esta direção. O que falta:</p>
                    <ul>
                        @forelse ($indisponiveis as $item)
                            <li><strong>{{ $item['nome'] }}</strong> ({{ $item['ponto'] }}): {{ implode(' ', $item['motivos']) }}</li>
                        @empty
                            <li>O posto não atende nenhum ponto de acesso real.</li>
                        @endforelse
                    </ul>
                </div>
            @endif

            @if ($excepcional)
                <x-ui.field id="lib-identificacao" label="Pessoa ou ocorrência (opcional)" wire:model="identificacao" placeholder="Ex.: entregador sem cadastro" :error="$errors->first('identificacao')" />
                <x-ui.field id="lib-observacao" label="{{ $observacaoObrigatoria ? 'Observação' : 'Observação (opcional)' }}" wire:model="observacao" :error="$errors->first('observacao')" :required="$observacaoObrigatoria" />
            @else
                <x-ui.field id="lib-motivo" label="Motivo da liberação" wire:model="motivo" :error="$errors->first('motivo')" required />
            @endif

            @error('liberacao')
                <x-ui.alert variant="danger" title="Liberação não enviada">{{ $message }}</x-ui.alert>
            @enderror

            @if ($confirmando && $escolhido)
                @unless ($escolhido['do_posto'])
                    <x-ui.alert variant="danger" title="Equipamento fora do seu posto">
                        Você está operando no posto {{ $posto->nome }} e selecionou o ponto {{ $escolhido['ponto']->nome }}. Confirma o acionamento deste relé?
                    </x-ui.alert>
                @endunless
                @if ($excepcional)
                    <x-ui.alert variant="danger" title="Liberação excepcional">
                        Você está liberando sem autorização regular confirmada. O evento ficará marcado como excepcional para revisão.
                    </x-ui.alert>
                @endif
                <x-ui.alert variant="warning" title="Confirmar liberação">
                    Você está prestes a acionar {{ $escolhido['equipamento']->nome }}, no ponto {{ $escolhido['ponto']->nome }}. Confirma a liberação?
                </x-ui.alert>
                <footer class="equipamento-management-acoes">
                    <x-ui.button variant="secondary" wire:click="cancelar">Cancelar</x-ui.button>
                    <x-ui.button variant="danger" wire:click="confirmar" wire:loading.attr="disabled" wire:target="confirmar">Confirmar liberação</x-ui.button>
                </footer>
            @else
                <footer class="equipamento-management-acoes">
                    <x-ui.button variant="primary" wire:click="revisar">Revisar liberação</x-ui.button>
                </footer>
            @endif
        @endif
    </x-ui.card>
</div>

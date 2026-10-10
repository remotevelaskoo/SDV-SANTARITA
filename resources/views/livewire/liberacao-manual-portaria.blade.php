<div class="liberacao-manual">
    <x-ui.card title="Liberação manual" description="Acione o equipamento do seu posto para liberar uma entrada ou saída. Cada liberação gera evento de acesso e auditoria.">
        @if (! $podeLiberar)
            <p class="equipamento-management-aviso">Você não tem a permissão de liberação manual.</p>
        @elseif (! $habilitada)
            <x-ui.alert variant="warning" title="Liberação manual desligada">
                A liberação operacional está desligada nesta instalação até a homologação e a aprovação formal.
            </x-ui.alert>
        @elseif ($postos->isEmpty())
            <p class="equipamento-management-aviso">Você não está designado para nenhum posto. Peça ao administrador para designá-lo.</p>
        @elseif ($evento)
            @php
                $tecnico = $evento->resultado_tecnico;
                [$rotulo, $variante, $texto] = match ($tecnico) {
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
                <p><x-ui.badge :variant="$variante">{{ $rotulo }}</x-ui.badge> {{ $texto }}</p>
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
            <div class="liberacao-manual__modo" role="group" aria-label="Tipo de liberação">
                <x-ui.button :variant="$modo === 'pessoa' ? 'primary' : 'secondary'" size="sm" wire:click="alterarModo('pessoa')">Pessoa cadastrada</x-ui.button>
                @if ($podeExcepcional)
                    <x-ui.button :variant="$modo === 'excepcional' ? 'danger' : 'secondary'" size="sm" wire:click="alterarModo('excepcional')">Liberação excepcional</x-ui.button>
                @endif
            </div>

            @if ($modo === 'pessoa')
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
            @else
                <x-ui.alert variant="warning" title="Liberação excepcional">
                    Use só quando não há cadastro. Exige identificação, justificativa e fica em auditoria reforçada.
                </x-ui.alert>
                <x-ui.field id="lib-identificacao" label="Quem está sendo liberado ou ocorrência" wire:model="identificacao" :error="$errors->first('identificacao')" required />
                <x-ui.field id="lib-justificativa" label="Justificativa (mínimo 20 caracteres)" wire:model="justificativa" :error="$errors->first('justificativa')" required />
            @endif

            <div class="equipamento-management-grid">
                <x-ui.select id="lib-posto" label="Posto" wire:model.live="postoId" :error="$errors->first('postoId')">
                    <option value="">Escolha o posto</option>
                    @foreach ($postos as $posto)
                        <option value="{{ $posto->id }}">{{ $posto->nome }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select id="lib-direcao" label="Direção" wire:model.live="direcao" :error="$errors->first('direcao')">
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                </x-ui.select>
                <x-ui.select id="lib-equipamento" label="Ponto e equipamento" wire:model.live="equipamentoId" :error="$errors->first('equipamentoId')">
                    <option value="">{{ $permitidos === [] ? 'Nenhum equipamento disponível' : 'Escolha o equipamento' }}</option>
                    @foreach ($permitidos as $item)
                        <option value="{{ $item['equipamento']->id }}">{{ $item['equipamento']->nome }} — {{ $item['ponto']->nome }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            @if ($escolhido)
                <p class="equipamento-management-resultado">
                    <strong>Será acionado:</strong> {{ $escolhido['equipamento']->nome }}, no ponto {{ $escolhido['ponto']->nome }}
                    <x-ui.badge :variant="$escolhido['equipamento']->estado_saude->value === 'conectado' ? 'success' : 'neutral'">{{ $escolhido['equipamento']->estado_saude->value === 'conectado' ? 'Online' : 'Offline' }}</x-ui.badge>
                </p>
            @elseif ($postoId && $permitidos === [])
                <p class="equipamento-management-aviso">Nenhum equipamento deste posto atende a direção escolhida com todos os requisitos (ativo, online, homologado e com relé homologado).</p>
            @endif

            <x-ui.field id="lib-motivo" label="Motivo da liberação" wire:model="motivo" :error="$errors->first('motivo')" required />

            @error('liberacao')
                <x-ui.alert variant="danger" title="Liberação não enviada">{{ $message }}</x-ui.alert>
            @enderror

            @if ($confirmando && $escolhido)
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

@php
    $tipos = [
        'principal' => ['rotulo' => 'Principal', 'explica' => 'pode acionar todos os relés autorizados'],
        'veiculos' => ['rotulo' => 'Veículos', 'explica' => 'mostra primeiro os pontos de veículos'],
        'pedestres' => ['rotulo' => 'Pedestres', 'explica' => 'mostra primeiro os pontos de pedestres'],
    ];
@endphp

<div class="equipamento-management postos-gestao">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">{{ $feedback['message'] }}</x-ui.toast>
    @endif

    <x-ui.alert variant="info" title="Como funcionam os postos">
        Qualquer operador pode assumir qualquer posto livre, mas cada posto fica com uma pessoa por vez e tem o seu próprio caixa.
        Para liberar o posto, o operador fecha o caixa e encerra o turno.
        @if ($podeLiberar)
            Se o posto ficar preso (computador travado, operador que saiu sem fechar), use <b>Liberar posto</b>.
        @endif
    </x-ui.alert>

    @if ($podeGerenciar)
        @if ($mostrarNovo)
            <x-ui.card title="Novo posto" description="Guarita ou posto de trabalho da portaria.">
                <form wire:submit="criarPosto" class="equipamento-management-form">
                    <div class="equipamento-management-grid">
                        <x-ui.field id="posto-codigo" label="Código" wire:model="codigo" placeholder="GUARITA-PRINCIPAL" help="Letras, números e hífen. Não muda depois." required />
                        <x-ui.field id="posto-nome" label="Nome" wire:model="nome" placeholder="Guarita Principal" required />
                        <x-ui.select id="posto-tipo" label="Tipo" wire:model="tipo">
                            @foreach ($tipos as $valor => $t)
                                <option value="{{ $valor }}">{{ $t['rotulo'] }} — {{ $t['explica'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <footer class="equipamento-management-acoes">
                        <x-ui.button type="submit" variant="primary">Cadastrar posto</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="$set('mostrarNovo', false)">Cancelar</x-ui.button>
                    </footer>
                </form>
            </x-ui.card>
        @else
            <div class="equipamento-management-acoes postos-gestao__topo">
                <x-ui.button variant="secondary" wire:click="$set('mostrarNovo', true)">+ Novo posto</x-ui.button>
            </div>
        @endif
    @endif

    @forelse ($postos as $posto)
        @php
            $ocupacao = $ocupacoes->get($posto->id);
            $tipo = $tipos[$posto->tipo] ?? ['rotulo' => $posto->tipo, 'explica' => ''];
            $vinculados = $posto->pontosVigentes->pluck('id');
        @endphp
        <x-ui.card wire:key="posto-{{ $posto->id }}" :class="$posto->ativo() ? '' : 'postos-gestao__inativo'" :title="$posto->nome" :description="'Código '.$posto->codigo.' · '.$tipo['rotulo'].': '.$tipo['explica']">
            <div class="equipamento-management-estado">
                @if (! $posto->ativo())
                    <x-ui.badge>Desativado</x-ui.badge>
                @elseif ($ocupacao)
                    <x-ui.badge variant="warning">Ocupado por {{ $ocupacao->user?->name ?? 'operador' }} desde {{ $ocupacao->iniciado_em->format('d/m H:i') }}</x-ui.badge>
                @else
                    <x-ui.badge variant="success">Livre</x-ui.badge>
                @endif
            </div>

            @if ($editandoId === $posto->id)
                <form wire:submit="salvarEdicao" class="equipamento-management-form">
                    <div class="equipamento-management-grid">
                        <x-ui.field id="editar-nome-{{ $posto->id }}" label="Nome" wire:model="editNome" required />
                        <x-ui.select id="editar-tipo-{{ $posto->id }}" label="Tipo" wire:model="editTipo">
                            @foreach ($tipos as $valor => $t)
                                <option value="{{ $valor }}">{{ $t['rotulo'] }} — {{ $t['explica'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <footer class="equipamento-management-acoes">
                        <x-ui.button type="submit" variant="primary" size="sm">Salvar</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="cancelarEdicao">Cancelar</x-ui.button>
                    </footer>
                </form>
            @elseif ($liberandoId === $posto->id)
                <form wire:submit="liberar" class="equipamento-management-form">
                    <p class="equipamento-management-aviso">
                        O turno de {{ $ocupacao?->user?->name ?? 'operador' }} será encerrado. Se o caixa estiver aberto, ele é fechado como
                        <b>aguardando conferência</b>, sem valor informado, para o gestor conferir depois.
                    </p>
                    <x-ui.field id="motivo-liberacao-{{ $posto->id }}" label="Motivo da liberação" wire:model="motivoLiberacao" placeholder="Ex.: computador travou; operador saiu sem fechar" required />
                    <footer class="equipamento-management-acoes">
                        <x-ui.button type="submit" variant="danger" size="sm">Confirmar liberação</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="cancelarLiberacao">Cancelar</x-ui.button>
                    </footer>
                </form>
            @else
                <div class="equipamento-management-acoes">
                    @if ($ocupacao && $podeLiberar)
                        <x-ui.button variant="danger" size="sm" wire:click="iniciarLiberacao('{{ $posto->id }}')">Liberar posto</x-ui.button>
                    @endif
                    @if ($podeGerenciar)
                        <x-ui.button variant="secondary" size="sm" wire:click="editar('{{ $posto->id }}')">Editar</x-ui.button>
                        @if ($posto->ativo())
                            <x-ui.button variant="ghost" size="sm" wire:click="alterarStatus('{{ $posto->id }}', false)" wire:confirm="Desativar o posto {{ $posto->nome }}? Ele deixa de aparecer para os operadores.">Desativar</x-ui.button>
                        @else
                            <x-ui.button variant="ghost" size="sm" wire:click="alterarStatus('{{ $posto->id }}', true)">Reativar</x-ui.button>
                        @endif
                    @endif
                </div>
            @endif

            <details class="postos-gestao__pontos">
                <summary>Pontos de acesso atendidos ({{ $posto->pontosVigentes->count() }})</summary>
                <ul class="equipamento-management-historico">
                    @forelse ($posto->pontosVigentes as $ponto)
                        <li>
                            <span>{{ $ponto->nome }} ({{ $ponto->tipo->value }})</span>
                            @if ($podeGerenciar)
                                <x-ui.button variant="ghost" size="sm" wire:click="desvincularPonto('{{ $posto->id }}', '{{ $ponto->id }}')">Remover</x-ui.button>
                            @endif
                        </li>
                    @empty
                        <li>Nenhum ponto vinculado.</li>
                    @endforelse
                </ul>
                @php($disponiveis = $pontos->whereNotIn('id', $vinculados))
                @if ($podeGerenciar && $disponiveis->isNotEmpty())
                    <div class="equipamento-management-acoes">
                        <x-ui.select id="ponto-{{ $posto->id }}" label="Adicionar ponto" wire:model="pontoEscolhido.{{ $posto->id }}">
                            <option value="">Escolha o ponto</option>
                            @foreach ($disponiveis as $ponto)
                                <option value="{{ $ponto->id }}">{{ $ponto->nome }} ({{ $ponto->tipo->value }})</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.button variant="secondary" size="sm" wire:click="vincularPonto('{{ $posto->id }}')">Vincular ponto</x-ui.button>
                    </div>
                @endif
            </details>
        </x-ui.card>
    @empty
        <x-ui.empty-state title="Nenhum posto cadastrado" description="Cadastre a guarita e vincule os pontos de acesso que ela atende." />
    @endforelse
</div>

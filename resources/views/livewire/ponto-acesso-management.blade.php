<div class="equipamento-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">{{ $feedback['message'] }}</x-ui.toast>
    @endif

    <x-ui.card title="Novo ponto de acesso" description="Cancela ou catraca real. A bancada de testes é criada pelo cadastro do equipamento e nunca é ativada.">
        <form wire:submit="cadastrar" class="equipamento-management-form">
            <div class="equipamento-management-grid">
                <x-ui.field id="ponto-codigo" label="Código" wire:model="form.codigo" placeholder="CANCELA-ENTRADA" :error="$errors->first('form.codigo')" required />
                <x-ui.field id="ponto-nome" label="Nome" wire:model="form.nome" placeholder="Entrada Principal" :error="$errors->first('form.nome')" required />
                <x-ui.select id="ponto-tipo" label="Tipo" wire:model="form.tipo">
                    <option value="cancela">Cancela</option>
                    <option value="catraca">Catraca</option>
                </x-ui.select>
                <x-ui.select id="ponto-direcao" label="Direção suportada" wire:model="form.direcao_suportada">
                    <option value="bidirecional">Bidirecional</option>
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                </x-ui.select>
                <x-ui.field id="ponto-localizacao" label="Localização" wire:model="form.localizacao" placeholder="Portão principal" />
            </div>
            <footer><x-ui.button type="submit" variant="primary">Cadastrar ponto</x-ui.button></footer>
        </form>
    </x-ui.card>

    <x-ui.card title="Motivo das alterações" description="Obrigatório para mudar a situação de um ponto ou terminal e para desvincular. Fica na auditoria.">
        <div class="equipamento-management-form">
            <x-ui.field id="ponto-motivo" label="Motivo" wire:model="motivo" placeholder="Ex.: instalação da cancela concluída e contato seco validado" :error="$errors->first('motivo')" />
        </div>
    </x-ui.card>

    @forelse ($pontos as $ponto)
        @php $terminal = $ponto->vinculoVigente?->equipamento; @endphp
        <x-ui.card :title="$ponto->nome" :description="$ponto->codigo.' · '.$ponto->tipo->value.' · '.$ponto->direcao_suportada->value.($ponto->localizacao ? ' · '.$ponto->localizacao : '')">
            <div class="equipamento-management-estado">
                <x-ui.badge :variant="$ponto->status->value === 'ativo' ? 'success' : 'neutral'">Ponto: {{ str_replace('_', ' ', $ponto->status->value) }}</x-ui.badge>
                @if ($terminal)
                    <x-ui.badge variant="info">Terminal: {{ $terminal->nome }} ({{ $terminal->status->rotulo() }})</x-ui.badge>
                    <x-ui.badge :variant="$terminal->rele_homologado_em ? 'success' : 'warning'">{{ $terminal->rele_homologado_em ? 'Relé homologado' : 'Relé não homologado' }}</x-ui.badge>
                @else
                    <x-ui.badge variant="warning">Sem terminal</x-ui.badge>
                @endif
            </div>

            <div class="equipamento-management-acoes">
                @if ($ponto->tipo !== $bancada && $ponto->status->value !== 'inativo')
                    @if ($ponto->status->value !== 'ativo')
                        <x-ui.button variant="primary" size="sm" wire:click="alterarSituacaoPonto('{{ $ponto->id }}', 'ativo')">Ativar ponto</x-ui.button>
                    @else
                        <x-ui.button variant="secondary" size="sm" wire:click="alterarSituacaoPonto('{{ $ponto->id }}', 'manutencao')">Colocar em manutenção</x-ui.button>
                    @endif
                @endif

                @if ($terminal)
                    @if ($ponto->tipo !== $bancada && $terminal->status->value !== 'ativo')
                        <x-ui.button variant="primary" size="sm" wire:click="alterarSituacaoTerminal('{{ $terminal->id }}', 'ativo')">Ativar terminal</x-ui.button>
                    @endif
                    @if ($terminal->status->value === 'ativo')
                        <x-ui.button variant="secondary" size="sm" wire:click="alterarSituacaoTerminal('{{ $terminal->id }}', 'em_homologacao')">Voltar terminal para homologação</x-ui.button>
                    @endif
                    <x-ui.button variant="ghost" size="sm" wire:click="desvincularTerminal('{{ $terminal->id }}')">Desvincular terminal</x-ui.button>
                @elseif ($ponto->status->value !== 'inativo')
                    <x-ui.select id="terminal-{{ $ponto->id }}" label="Terminal" wire:model="terminalEscolhido.{{ $ponto->id }}">
                        <option value="">{{ $terminaisLivres->isEmpty() ? 'Nenhum terminal disponível' : 'Escolha o terminal' }}</option>
                        @foreach ($terminaisLivres as $livre)
                            <option value="{{ $livre->id }}">{{ $livre->nome }} ({{ $livre->direcao->value }}){{ $livre->vinculoVigente ? ' — hoje na '.$livre->vinculoVigente->pontoAcesso?->nome.', será movido' : '' }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button variant="secondary" size="sm" wire:click="vincularTerminal('{{ $ponto->id }}')">Vincular terminal</x-ui.button>
                @endif
            </div>
        </x-ui.card>
    @empty
        <x-ui.empty-state title="Nenhum ponto de acesso" description="Cadastre a cancela ou catraca real." />
    @endforelse
</div>

<div class="equipamento-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">{{ $feedback['message'] }}</x-ui.toast>
    @endif

    <x-ui.card title="Novo posto" description="Guarita ou posto de trabalho da portaria.">
        <form wire:submit="criarPosto" class="equipamento-management-form">
            <div class="equipamento-management-grid">
                <x-ui.field id="posto-codigo" label="Código" wire:model="codigo" placeholder="GUARITA-01" required />
                <x-ui.field id="posto-nome" label="Nome" wire:model="nome" placeholder="Guarita Principal" required />
                <x-ui.select id="posto-tipo" label="Tipo" wire:model="tipo">
                    <option value="principal">Principal (todos os relés autorizados)</option>
                    <option value="veiculos">Atendimento de veículos</option>
                    <option value="pedestres">Atendimento de pedestres</option>
                </x-ui.select>
            </div>
            <footer><x-ui.button type="submit" variant="primary">Cadastrar posto</x-ui.button></footer>
        </form>
    </x-ui.card>

    @forelse ($postos as $posto)
        <x-ui.card :title="$posto->nome" :description="$posto->codigo.' · '.$posto->tipo.' · '.($posto->ativo() ? 'ativo' : $posto->status)">
            <div class="equipamento-management-grid equipamento-management-form">
                <section>
                    <h4>Pontos de acesso atendidos</h4>
                    <ul class="equipamento-management-historico">
                        @forelse ($posto->pontosVigentes as $ponto)
                            <li>
                                <span>{{ $ponto->nome }} ({{ $ponto->tipo->value }})</span>
                                <x-ui.button variant="ghost" size="sm" wire:click="desvincularPonto('{{ $posto->id }}', '{{ $ponto->id }}')">Remover</x-ui.button>
                            </li>
                        @empty
                            <li>Nenhum ponto vinculado.</li>
                        @endforelse
                    </ul>
                    <x-ui.select id="ponto-{{ $posto->id }}" label="Adicionar ponto" wire:model="pontoEscolhido.{{ $posto->id }}">
                        <option value="">Escolha o ponto</option>
                        @foreach ($pontos as $ponto)
                            <option value="{{ $ponto->id }}">{{ $ponto->nome }} ({{ $ponto->tipo->value }})</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button variant="secondary" size="sm" wire:click="vincularPonto('{{ $posto->id }}')">Vincular ponto</x-ui.button>
                </section>

                <section>
                    <h4>Operadores designados</h4>
                    <ul class="equipamento-management-historico">
                        @forelse ($operadores->get($posto->id, collect()) as $designacao)
                            <li>
                                <span>{{ $designacao->user?->name }} · desde {{ $designacao->started_at->format('d/m/Y H:i') }}</span>
                                <x-ui.button variant="ghost" size="sm" wire:click="encerrarDesignacao('{{ $posto->id }}', {{ $designacao->user_id }})">Encerrar</x-ui.button>
                            </li>
                        @empty
                            <li>Nenhum operador designado.</li>
                        @endforelse
                    </ul>
                    <x-ui.select id="operador-{{ $posto->id }}" label="Designar operador" wire:model="operadorEscolhido.{{ $posto->id }}">
                        <option value="">Escolha o operador</option>
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button variant="secondary" size="sm" wire:click="designarOperador('{{ $posto->id }}')">Designar</x-ui.button>
                </section>
            </div>
        </x-ui.card>
    @empty
        <x-ui.empty-state title="Nenhum posto cadastrado" description="Cadastre a guarita e vincule os pontos de acesso que ela atende." />
    @endforelse
</div>

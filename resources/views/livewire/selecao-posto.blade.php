<div class="equipamento-management">
    @if ($erro)
        <x-ui.toast variant="danger" title="Posto não assumido" :dismissible="false">{{ $erro }}</x-ui.toast>
    @endif

    @if ($turno)
        <x-ui.alert variant="info" title="Posto ativo: {{ $turno->posto->nome }}">
            Desde {{ $turno->iniciado_em->format('d/m/Y H:i') }}. Escolha outro posto para trocar ou encerre o turno.
        </x-ui.alert>
    @endif

    @if ($postos->isEmpty())
        <x-ui.empty-state title="Nenhum posto disponível" description="Você não está designado para nenhum posto. Peça ao administrador em Gestão › Postos e operadores." />
    @else
        <div class="posto-selecao">
            @foreach ($postos as $posto)
                <x-ui.card :title="$posto->nome" :description="match ($posto->tipo) { 'principal' => 'Alcança todos os relés autorizados', 'veiculos' => 'Prioriza os pontos de veículos', 'pedestres' => 'Prioriza os pontos de pedestres', default => '' }">
                    <div class="equipamento-management-acoes">
                        @if ($turno?->posto_id === $posto->id)
                            <x-ui.badge variant="success">Posto atual</x-ui.badge>
                        @else
                            <x-ui.button variant="primary" wire:click="assumir('{{ $posto->id }}')">{{ $turno ? 'Trocar para este posto' : 'Assumir este posto' }}</x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    @if ($turno)
        <x-ui.card title="Câmeras deste computador" description="Associe as câmeras USB deste computador ao posto {{ $turno->posto->nome }}. A escolha fica salva só neste navegador.">
            <div class="captura-documento" wire:key="cameras-{{ $turno->posto_id }}"
                x-data="camerasDoPosto({ implantacaoId: @js($turno->implantacao_id), postoId: @js($turno->posto_id), modo: 'configuracao' })">
                <p class="equipamento-management-aviso" x-show="aviso" x-text="aviso"></p>
                <div class="equipamento-management-grid">
                    @foreach (['documentos' => 'Câmera de documentos', 'placas' => 'Câmera de placas'] as $finalidade => $rotulo)
                        <label class="ui-field">
                            <span class="ui-field__label">{{ $rotulo }}</span>
                            <span class="ui-select-control">
                                <select class="ui-field__control ui-field__select" x-model="escolhas.{{ $finalidade }}" x-on:change="escolher('{{ $finalidade }}', $event.target.value)">
                                    <option value="">Escolha a câmera</option>
                                    <template x-for="camera in cameras" :key="camera.deviceId">
                                        <option :value="camera.deviceId" x-text="camera.label || 'Câmera sem nome (autorize o acesso)'"></option>
                                    </template>
                                </select>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="equipamento-management-acoes">
                    <button type="button" class="ui-button ui-button--secondary ui-button--sm" x-on:click="autorizar()"><span class="ui-button__content">Autorizar câmeras</span></button>
                </div>
            </div>
        </x-ui.card>

        <div class="equipamento-management-acoes">
            <x-ui.button variant="secondary" wire:click="encerrar">Encerrar turno no posto</x-ui.button>
            <x-ui.button variant="ghost" :href="route('portaria')">Ir para o Modo Portaria</x-ui.button>
        </div>
    @endif
</div>

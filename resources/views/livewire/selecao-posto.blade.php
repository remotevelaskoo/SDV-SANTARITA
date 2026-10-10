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
        <div class="equipamento-management-acoes">
            <x-ui.button variant="secondary" wire:click="encerrar">Encerrar turno no posto</x-ui.button>
            <x-ui.button variant="ghost" :href="route('portaria')">Ir para o Modo Portaria</x-ui.button>
        </div>
    @endif
</div>

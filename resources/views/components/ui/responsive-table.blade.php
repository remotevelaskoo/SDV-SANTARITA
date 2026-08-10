@props([
    'label',
    'state' => 'ready',
    'emptyTitle' => 'Nenhum registro encontrado',
    'emptyDescription' => 'Revise os filtros ou crie um novo registro autorizado.',
    'errorTitle' => 'Não foi possível carregar os registros',
    'errorDescription' => 'Tente novamente. Se o problema continuar, procure o suporte.',
])

<div {{ $attributes->class(['ui-responsive-table']) }} @if ($state === 'loading') aria-busy="true" @endif>
    @if ($state === 'loading')
        <div class="ui-table-loading" role="status">
            <span class="sr-only">Carregando {{ $label }}</span>
            @for ($row = 0; $row < 4; $row++)
                <span aria-hidden="true"></span>
            @endfor
        </div>
    @elseif ($state === 'empty')
        <x-ui.empty-state :title="$emptyTitle" :description="$emptyDescription" />
    @elseif ($state === 'error')
        <div class="ui-table-state">
            <x-ui.alert variant="danger" :title="$errorTitle">
                {{ $errorDescription }}
                @if (isset($retry))
                    <x-slot:action>{{ $retry }}</x-slot:action>
                @endif
            </x-ui.alert>
        </div>
    @else
        <div class="ui-responsive-table__desktop">
            <table>
                <caption class="sr-only">{{ $label }}</caption>
                {{ $table }}
            </table>
        </div>
        <div class="ui-responsive-table__mobile" aria-label="{{ $label }}">
            {{ $cards }}
        </div>
    @endif
</div>

@props([
    'current' => 1,
    'total' => 1,
    'from' => 1,
    'to' => 1,
    'totalItems' => 1,
])

<nav aria-label="Paginação" {{ $attributes->class('ui-pagination') }}>
    <p>Exibindo <strong>{{ $from }}–{{ $to }}</strong> de <strong>{{ $totalItems }}</strong></p>
    <div class="ui-pagination__controls">
        <button type="button" aria-label="Página anterior" @disabled($current <= 1)>
            <x-icon name="chevron-left" />
        </button>
        @for ($page = 1; $page <= $total; $page++)
            <button
                type="button"
                @if ($page === $current) aria-current="page" @endif
                aria-label="Página {{ $page }}"
            >{{ $page }}</button>
        @endfor
        <button type="button" aria-label="Próxima página" @disabled($current >= $total)>
            <x-icon name="chevron-right" />
        </button>
    </div>
</nav>

@php
    $groups = \App\Support\Navigation::groups(auth()->user());
    // Inclui subpáginas, como a análise de um pré-cadastro (pre-registrations.review).
    $isCurrent = fn (array $item): bool => request()->routeIs($item['route'], $item['route'].'.*');
    // Páginas fora do menu (Minhas sessões, posto, implantação) abrem o primeiro grupo.
    $hasCurrent = collect($groups)->flatten(1)->contains($isCurrent);
@endphp

<nav class="navigation" aria-label="Módulos do sistema">
    @foreach ($groups as $group => $items)
        <details class="navigation__group" @if (collect($items)->contains($isCurrent) || $group === 'Visão geral' || (! $hasCurrent && $loop->first)) open @endif>
            <summary>{{ $group }}</summary>
            <ul>
                @foreach ($items as $item)
                    @php($current = $isCurrent($item))
                    <li>
                        <a
                            @class(['navigation__item', 'navigation__item--active' => $current])
                            href="{{ route($item['route']) }}"
                            @if ($current) aria-current="page" @endif
                        >
                            <x-icon :name="$item['icon']" />
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </details>
    @endforeach
</nav>

@php
    $groups = \App\Support\Navigation::groups(auth()->user());
@endphp

<nav class="navigation" aria-label="Módulos do sistema">
    @foreach ($groups as $group => $items)
        @php
            $active = collect($items)->contains(fn ($item) => request()->routeIs($item['route'], $item['route'].'.*'));
            if ($group === 'Operação' && request()->routeIs('pre-registrations.review')) {
                $active = true;
            }
        @endphp
        <details class="navigation__group" @if ($active || $group === 'Visão geral') open @endif>
            <summary>{{ $group }}</summary>
            <ul>
                @foreach ($items as $item)
                    @php($current = request()->routeIs($item['route'], $item['route'].'.*'))
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

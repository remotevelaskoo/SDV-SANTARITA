@props(['context' => 'sidebar'])

@php
    // Itens já filtrados no servidor pelas permissões efetivas da implantação
    // atual (AJ-009). Recolher grupos, compactar a barra ou abrir a gaveta só
    // mudam a apresentação: nada que não esteja aqui chega ao navegador.
    $groups = \App\Support\Navigation::groups(auth()->user());
    $currentRoute = \App\Support\Navigation::currentRoute($groups);
    $isCurrent = fn (array $item): bool => $item['route'] === $currentRoute;
@endphp

<nav class="navigation" aria-label="Módulos do sistema" data-navigation="{{ $context }}">
    @foreach ($groups as $group => $items)
        @php
            $id = 'nav-'.$context.'-'.\Illuminate\Support\Str::slug($group);
            $open = collect($items)->contains($isCurrent) || $group === 'Visão geral' || ($currentRoute === null && $loop->first);
        @endphp
        <section @class(['navigation__group', 'is-open' => $open]) data-nav-group>
            <h2 class="navigation__heading">
                <button
                    type="button"
                    class="navigation__group-toggle"
                    aria-expanded="{{ $open ? 'true' : 'false' }}"
                    aria-controls="{{ $id }}"
                    data-nav-group-toggle
                >
                    <span>{{ $group }}</span>
                    <x-icon name="chevron-down" />
                </button>
            </h2>
            <div class="navigation__panel" id="{{ $id }}" data-nav-group-panel>
                <ul aria-label="{{ $group }}">
                    @foreach ($items as $item)
                        @php($current = $isCurrent($item))
                        <li>
                            <a
                                @class(['navigation__item', 'navigation__item--active' => $current])
                                href="{{ route($item['route']) }}"
                                data-nav-label="{{ $item['label'] }}"
                                @if ($current) aria-current="page" @endif
                            >
                                <x-icon :name="$item['icon']" />
                                <span class="navigation__label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endforeach
</nav>

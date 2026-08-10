@php
    $groups = [
        'Operação' => [
            ['label' => 'Modo Portaria', 'icon' => 'door', 'route' => 'portaria'],
            ['label' => 'Dashboard', 'icon' => 'grid', 'route' => 'dashboard'],
            ['label' => 'Validação de entrada', 'icon' => 'shield', 'route' => 'validation'],
            ['label' => 'Pré-cadastro', 'icon' => 'badge-check', 'counter' => 3, 'route' => 'pre-registrations'],
            ['label' => 'Entradas e saídas', 'icon' => 'door'],
        ],
        'Cadastros' => [
            ['label' => 'Imóveis', 'icon' => 'building', 'route' => 'properties'],
            ['label' => 'Pessoas', 'icon' => 'users'],
            ['label' => 'Empresas e prestadores', 'icon' => 'users-round'],
            ['label' => 'Veículos', 'icon' => 'car', 'route' => 'vehicles'],
        ],
        'Gestão' => [
            ['label' => 'Administração', 'icon' => 'settings'],
            ['label' => 'Relatórios', 'icon' => 'chart'],
            ['label' => 'Encomendas', 'icon' => 'package'],
            ['label' => 'Logs e auditoria', 'icon' => 'scroll'],
            ['label' => 'Manutenção', 'icon' => 'wrench'],
            ['label' => 'Caixa', 'icon' => 'clipboard'],
        ],
    ];
@endphp

<nav class="navigation" aria-label="Módulos do sistema">
    @foreach ($groups as $group => $items)
        <section class="navigation__group" aria-labelledby="nav-{{ Str::slug($group) }}">
            <h2 id="nav-{{ Str::slug($group) }}">{{ $group }}</h2>
            <ul>
                @foreach ($items as $item)
                    <li>
                        @if (isset($item['route']))
                            <a
                                @class(['navigation__item', 'navigation__item--active' => request()->routeIs($item['route'])])
                                href="{{ route($item['route']) }}"
                                @if (request()->routeIs($item['route'])) aria-current="page" @endif
                            >
                                <x-icon :name="$item['icon']" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @else
                            <span class="navigation__item navigation__item--disabled" aria-disabled="true" title="Módulo será portado em uma próxima etapa">
                                <x-icon :name="$item['icon']" />
                                <span>{{ $item['label'] }}</span>
                                @if (isset($item['counter']))
                                    <small class="navigation__counter">{{ $item['counter'] }}</small>
                                @endif
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</nav>

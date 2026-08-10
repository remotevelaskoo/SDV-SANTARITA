@props([
    'property',
    'nature',
    'responsibility',
    'period',
    'status' => 'Vínculo ativo',
    'tone' => 'success',
    'permissions' => [],
])

<article {{ $attributes->class('ui-link-panel') }}>
    <header><div><span>Vínculo com imóvel</span><strong>{{ $property }}</strong></div><x-ui.badge :variant="$tone">{{ $status }}</x-ui.badge></header>
    <dl><div><dt>Natureza</dt><dd>{{ $nature }}</dd></div><div><dt>Responsabilidade</dt><dd>{{ $responsibility }}</dd></div><div><dt>Período</dt><dd>{{ $period }}</dd></div></dl>
    @if (count($permissions))<div class="ui-link-panel__permissions"><span>Permissões derivadas</span><ul>@foreach ($permissions as $permission)<li><x-icon name="check" />{{ $permission }}</li>@endforeach</ul></div>@endif
    @if (isset($actions))<footer>{{ $actions }}</footer>@endif
</article>

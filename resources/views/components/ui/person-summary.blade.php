@props([
    'name',
    'initials',
    'document',
    'type',
    'property',
    'responsible' => null,
    'status' => 'Cadastro ativo',
    'validity' => 'Acesso permanente',
    'tone' => 'success',
    'photoUrl' => null,
])

<article {{ $attributes->class('ui-person-summary') }}>
    <header>
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="" class="ui-person-summary__photo">
        @else
            <span class="ui-person-summary__avatar" aria-hidden="true">{{ $initials }}</span>
        @endif
        <div><strong>{{ $name }}</strong><small>{{ $type }}</small></div><x-ui.badge :variant="$tone">{{ $status }}</x-ui.badge></header>
    <dl>
        <div><dt>Documento</dt><dd>{{ $document }}</dd></div>
        <div><dt>Imóvel</dt><dd>{{ $property }}</dd></div>
        @if ($responsible)<div><dt>Responsável</dt><dd>{{ $responsible }}</dd></div>@endif
        <div><dt>Vigência</dt><dd>{{ $validity }}</dd></div>
    </dl>
    @if (isset($actions))<footer>{{ $actions }}</footer>@endif
</article>

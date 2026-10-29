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
    'photoState' => null,
    'photoOrigin' => null,
])

<article {{ $attributes->class('ui-person-summary') }}>
    <header @class(['has-photo' => $photoState !== null])>
        @if ($photoUrl)
            <figure class="ui-person-summary__photo">
                <img src="{{ $photoUrl }}" alt="Foto de {{ $name }}" loading="lazy" referrerpolicy="no-referrer">
                @if ($photoOrigin)<figcaption>{{ $photoOrigin }}</figcaption>@endif
            </figure>
        @elseif ($photoState !== null)
            <figure class="ui-person-summary__photo is-empty">
                <span aria-hidden="true"><x-icon name="users" /></span>
                <figcaption>{{ $photoState === 'sem_permissao' ? 'Foto protegida pelo seu perfil' : 'Sem foto cadastrada' }}</figcaption>
            </figure>
        @else
            <span class="ui-person-summary__avatar" aria-hidden="true">{{ $initials }}</span>
        @endif
        <div><strong>{{ $name }}</strong><small>{{ $type }}</small></div>
        <x-ui.badge :variant="$tone">{{ $status }}</x-ui.badge>
    </header>
    <dl>
        <div><dt>Documento</dt><dd>{{ $document }}</dd></div>
        <div><dt>Imóvel</dt><dd>{{ $property }}</dd></div>
        @if ($responsible)<div><dt>Responsável</dt><dd>{{ $responsible }}</dd></div>@endif
        <div><dt>Vigência</dt><dd>{{ $validity }}</dd></div>
    </dl>
    {{ $slot }}
    @if (isset($actions))<footer>{{ $actions }}</footer>@endif
</article>

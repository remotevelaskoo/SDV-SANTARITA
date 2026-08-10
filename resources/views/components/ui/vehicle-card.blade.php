@props([
    'plate',
    'model',
    'color',
    'owner',
    'link',
    'status' => 'Cadastrado',
    'tone' => 'success',
])

<article {{ $attributes->class('ui-vehicle-card') }}>
    <div class="ui-vehicle-card__visual"><x-icon name="car" /><span>{{ $plate }}</span></div>
    <div class="ui-vehicle-card__content"><header><div><strong>{{ $model }}</strong><small>{{ $color }}</small></div><x-ui.badge :variant="$tone">{{ $status }}</x-ui.badge></header><dl><div><dt>Proprietário</dt><dd>{{ $owner }}</dd></div><div><dt>Vínculo</dt><dd>{{ $link }}</dd></div></dl>@if (isset($actions))<footer>{{ $actions }}</footer>@endif</div>
</article>

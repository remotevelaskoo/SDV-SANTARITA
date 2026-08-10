@props([
    'number',
    'status',
    'datetime',
    'tone' => 'success',
])

<article x-data="{ copied: false }" {{ $attributes->class('ui-protocol') }}>
    <div><span>Protocolo do atendimento</span><button type="button" x-on:click="navigator.clipboard?.writeText('{{ $number }}'); copied = true; setTimeout(() => copied = false, 1800)" aria-label="Copiar protocolo"><x-icon name="copy" /></button></div>
    <strong>{{ $number }}</strong>
    <footer><x-ui.badge :variant="$tone">{{ $status }}</x-ui.badge><time>{{ $datetime }}</time></footer>
    <p x-show="copied" x-cloak role="status">Protocolo copiado.</p>
</article>

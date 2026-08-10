@props([
    'recognized',
    'registered',
    'confidence',
    'vehicle',
    'capturedAt',
])

@php $matches = strtoupper($recognized) === strtoupper($registered); @endphp

<article {{ $attributes->class(['ui-lpr', 'is-match' => $matches, 'has-divergence' => ! $matches]) }}>
    <div class="ui-lpr__capture"><span>Imagem capturada agora</span><x-icon name="car" /><strong>{{ $recognized }}</strong><small>{{ $capturedAt }}</small></div>
    <div class="ui-lpr__comparison">
        <header><div><span>Leitura da placa</span><strong>{{ $confidence }}% de confiança</strong></div><x-ui.badge :variant="$matches ? 'success' : 'danger'" :icon="$matches ? 'check-circle' : 'alert'">{{ $matches ? 'Placa confere' : 'Divergência' }}</x-ui.badge></header>
        <dl><div><dt>Reconhecida</dt><dd>{{ $recognized }}</dd></div><div><dt>Cadastrada</dt><dd>{{ $registered }}</dd></div><div><dt>Veículo</dt><dd>{{ $vehicle }}</dd></div></dl>
        <x-ui.progress type="bar" label="Confiança da leitura" :value="$confidence" />
        @if (isset($actions))<footer>{{ $actions }}</footer>@endif
    </div>
</article>

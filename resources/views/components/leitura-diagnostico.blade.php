@props(['diagnostico' => null, 'variant' => 'warning', 'replace' => 'removeDocumentFile'])

{{-- Motivo objetivo da leitura automática não concluída ou parcial (AJ-006). --}}
@if ($diagnostico && $diagnostico['motivos'] !== [])
    <x-ui.alert :variant="$variant" :title="$diagnostico['titulo']" {{ $attributes->class('leitura-diagnostico') }}>
        <ul>
            @foreach ($diagnostico['motivos'] as $motivo)
                <li>{{ $motivo }}</li>
            @endforeach
        </ul>
        <x-slot:action>
            @if ($diagnostico['recapturar'])
                <x-ui.button variant="secondary" size="sm" wire:click="{{ $replace }}">Enviar outra foto</x-ui.button>
            @endif
            <small>Você também pode digitar os dados manualmente. A leitura nunca aprova o cadastro sozinha.</small>
        </x-slot:action>
    </x-ui.alert>
@endif

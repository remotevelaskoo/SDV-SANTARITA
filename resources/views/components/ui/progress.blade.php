@props([
    'type' => 'spinner',
    'label' => 'Carregando…',
    'value' => null,
])

@if ($type === 'bar')
    <div class="ui-progress" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" @if (! is_null($value)) aria-valuenow="{{ $value }}" @endif>
        <div class="ui-progress__heading">
            <span>{{ $label }}</span>
            @if (! is_null($value))
                <strong>{{ $value }}%</strong>
            @endif
        </div>
        <span class="ui-progress__track" aria-hidden="true">
            <span style="--progress-value: {{ $value ?? 35 }}%"></span>
        </span>
    </div>
@else
    <div class="ui-loading" role="status" {{ $attributes }}>
        <span class="ui-spinner" aria-hidden="true"></span>
        <span>{{ $label }}</span>
    </div>
@endif

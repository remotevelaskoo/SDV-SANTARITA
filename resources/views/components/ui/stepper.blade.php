@props([
    'steps',
    'current' => 1,
])

<ol {{ $attributes->class('ui-stepper') }} aria-label="Etapas do processo">
    @foreach ($steps as $index => $step)
        @php
            $number = $index + 1;
            $state = $step['state'] ?? ($number < $current ? 'complete' : ($number === $current ? 'current' : 'future'));
        @endphp
        <li @class(["is-{$state}"]) @if ($state === 'current') aria-current="step" @endif>
            <span class="ui-stepper__marker" aria-hidden="true">
                @if ($state === 'complete')
                    <x-icon name="check" />
                @else
                    {{ $number }}
                @endif
            </span>
            <span class="ui-stepper__text">
                <strong>{{ $step['label'] }}</strong>
                @if (! empty($step['description']))
                    <small>{{ $step['description'] }}</small>
                @endif
            </span>
        </li>
    @endforeach
</ol>

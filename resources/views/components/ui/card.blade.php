@props([
    'variant' => 'default',
    'title' => null,
    'description' => null,
])

<article {{ $attributes->class(['ui-card', "ui-card--{$variant}"]) }}>
    @if ($title || $description || isset($headerAction))
        <header class="ui-card__header">
            <div>
                @if ($title)
                    <h3>{{ $title }}</h3>
                @endif
                @if ($description)
                    <p>{{ $description }}</p>
                @endif
            </div>
            @if (isset($headerAction))
                <div class="ui-card__header-action">{{ $headerAction }}</div>
            @endif
        </header>
    @endif

    <div class="ui-card__body">{{ $slot }}</div>

    @if (isset($footer))
        <footer class="ui-card__footer">{{ $footer }}</footer>
    @endif
</article>

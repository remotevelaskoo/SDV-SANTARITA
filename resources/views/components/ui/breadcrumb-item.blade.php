@props([
    'href' => null,
    'current' => false,
])

<li>
    @if ($current)
        <span aria-current="page">{{ $slot }}</span>
    @elseif ($href)
        <a href="{{ $href }}">{{ $slot }}</a>
        <x-icon name="chevron-right" />
    @else
        <span>{{ $slot }}</span>
        <x-icon name="chevron-right" />
    @endif
</li>

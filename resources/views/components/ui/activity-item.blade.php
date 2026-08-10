@props([
    'title',
    'description',
    'datetime',
    'actor' => null,
    'location' => null,
    'status' => null,
    'tone' => 'neutral',
    'icon' => 'clock',
])

<li class="ui-activity-item">
    <span class="ui-activity-item__icon"><x-icon :name="$icon" /></span>
    <div class="ui-activity-item__content">
        <div><strong>{{ $title }}</strong>@if ($status)<x-ui.badge :variant="$tone">{{ $status }}</x-ui.badge>@endif</div>
        <p>{{ $description }}</p>
        <small>{{ $datetime }}@if ($actor) · {{ $actor }}@endif @if ($location) · {{ $location }}@endif</small>
    </div>
</li>

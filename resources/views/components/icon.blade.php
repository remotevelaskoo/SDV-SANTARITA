@props(['name'])

<svg {{ $attributes->class('app-icon') }} aria-hidden="true" viewBox="0 0 24 24">
    @switch($name)
        @case('grid')
            <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            @break
        @case('users-round')
            <path d="M18 21a6 6 0 0 0-12 0M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 19a5 5 0 0 0-3.1-4.6M19 4.6a3.5 3.5 0 0 1 0 6.8M2 19a5 5 0 0 1 3.1-4.6M5 4.6a3.5 3.5 0 0 0 0 6.8" />
            @break
        @case('shield')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" /><path d="m9 12 2 2 4-4" />
            @break
        @case('badge-check')
            <path d="M12 2l2.1 2.3 3.1-.2.7 3 2.7 1.6-1.2 2.9 1.2 2.9-2.7 1.6-.7 3-3.1-.2L12 22l-2.1-2.3-3.1.2-.7-3-2.7-1.6 1.2-2.9-1.2-2.9 2.7-1.6.7-3 3.1.2L12 2Z" /><path d="m9 12 2 2 4-4" />
            @break
        @case('clipboard')
            <path d="M9 5H6a2 2 0 0 0-2 2v13h16V7a2 2 0 0 0-2-2h-3M9 3h6v4H9zM8 12h8M8 16h6" />
            @break
        @case('wallet')
            <path d="M3 6h16a2 2 0 0 1 2 2v11H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h15M16 11h5v4h-5a2 2 0 0 1 0-4Z" />
            @break
        @case('chart')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
            @break
        @case('car')
            <path d="m5 17-1 2M19 17l1 2M3 12l2-6h14l2 6v5H3zM7 14h.01M17 14h.01" />
            @break
        @case('door')
            <path d="M3 21h18M5 21V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v17M9 21V7h6v14M12 13h.01" />
            @break
        @case('building')
            <path d="M3 21h18M6 21V3h12v18M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2" />
            @break
        @case('package')
            <path d="m12 2 9 5-9 5-9-5 9-5ZM3 7v10l9 5 9-5V7M12 12v10" />
            @break
        @case('scroll')
            <path d="M8 18V4a2 2 0 0 1 2-2h9v16a4 4 0 0 1-4 4H6a3 3 0 0 1-3-3v-1h12v1a3 3 0 0 0 3 3M11 7h5M11 11h5M11 15h3" />
            @break
        @case('wrench')
            <path d="M14.7 6.3a4 4 0 0 0-5-5L12 3.6 8.6 7 6.3 4.7a4 4 0 0 0 5 5L4 17l3 3 7.3-7.3a4 4 0 0 0 5-5L17 10l-3.4-3.4 1.1-.3Z" />
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-2.83 2.83-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21h-4v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06-2.83-2.83.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3v-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06 2.83-2.83.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3h4v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06 2.83 2.83-.06.06A1.65 1.65 0 0 0 19.32 9c.12.61.66 1.05 1.29 1.05H21v4h-.09A1.65 1.65 0 0 0 19.4 15Z" />
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16" />
            @break
        @case('panel-left')
            <rect x="3" y="3" width="18" height="18" rx="2" /><path d="M9 3v18" />
            @break
        @case('chevrons-left')
            <path d="m11 17-5-5 5-5M18 17l-5-5 5-5" />
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break
        @case('chevron-left')
            <path d="m15 18-6-6 6-6" />
            @break
        @case('chevron-right')
            <path d="m9 18 6-6-6-6" />
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" />
            @break
        @case('upload')
            <path d="M12 16V4M7 9l5-5 5 5M4 20h16" />
            @break
        @case('download')
            <path d="M12 4v12M7 11l5 5 5-5M4 20h16" />
            @break
        @case('file')
            <path d="M6 2h8l4 4v16H6zM14 2v5h5" />
            @break
        @case('copy')
            <rect x="8" y="8" width="12" height="12" rx="2" /><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />
            @break
        @case('key')
            <circle cx="8" cy="15" r="4" /><path d="m11 12 9-9M16 4l4 4M14 6l2 2" />
            @break
        @case('refresh')
            <path d="M20 7v5h-5M4 17v-5h5M6.1 9a7 7 0 0 1 11.2-2.1L20 12M4 12l2.7 5.1A7 7 0 0 0 17.9 15" />
            @break
        @case('arrow-up-right')
            <path d="M7 17 17 7M7 7h10v10" />
            @break
        @case('arrow-down-right')
            <path d="m7 7 10 10M17 7v10H7" />
            @break
        @case('arrow-down-left')
            <path d="M17 7 7 17M17 17H7V7" />
            @break
        @case('minus')
            <path d="M5 12h14" />
            @break
        @case('alert')
            <path d="M10.3 3.5 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.5a2 2 0 0 0-3.4 0ZM12 9v4M12 17h.01" />
            @break
        @case('info')
            <circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" />
            @break
        @case('check-circle')
            <circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" />
            @break
        @case('check')
            <path d="m5 12 4 4L19 6" />
            @break
        @case('x')
            <path d="M6 6l12 12M18 6 6 18" />
            @break
        @case('inbox')
            <path d="M4 4h16l2 11v5H2v-5L4 4Z" /><path d="M2 15h5l2 3h6l2-3h5" />
            @break
        @case('video')
            <path d="M15 10 21 7v10l-6-3v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3Z" />
            @break
        @case('power')
            <path d="M12 2v10M6.3 5.7a8 8 0 1 0 11.4 0" />
            @break
    @endswitch
</svg>

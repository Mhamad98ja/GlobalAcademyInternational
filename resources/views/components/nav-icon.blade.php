@props(['name'])

<svg {{ $attributes->class('h-full w-full p-0.5') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
            @break
        @case('operations')
            <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v4.75l3.25 2"/>
            @break
        @case('students')
            <circle cx="9" cy="8" r="3"/><path d="M3.5 20c.5-3.5 2.5-5.5 5.5-5.5s5 2 5.5 5.5M16.5 4.5a2.75 2.75 0 0 1 0 5.25M17.5 14.75c1.75.5 2.75 2.25 3 4.25"/>
            @break
        @case('books')
            <path d="M4.5 5.5A2.5 2.5 0 0 1 7 3h11.5v16H7a2.5 2.5 0 0 0-2.5 2.5z"/><path d="M4.5 5.5V21M8.5 7h6"/>
            @break
        @case('suppliers')
            <path d="M3 6h11v11H3zM14 10h3l3 3v4h-6z"/><circle cx="7" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/>
            @break
        @case('distribution')
            <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9M8.5 5l8 4.5"/>
            @break
        @case('payments')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>
            @break
        @case('inventory')
            <path d="M4 7.5 8 5l4 2.5L8 10zM4 7.5V12l4 2.5V10M12 7.5V12L8 14.5M12 7.5 16 5l4 2.5-4 2.5zM12 7.5V12l4 2.5V10M20 7.5V12l-4 2.5M8 14.5l4-2.5 4 2.5L12 17zM8 14.5V19l4 2.5V17M16 14.5V19l-4 2.5"/>
            @break
        @case('academic')
            <path d="m3 9 9-5 9 5-9 5zM7 11.25V16c2.5 2 7.5 2 10 0v-4.75M20 10v5"/>
            @break
        @case('teachers')
            <path d="M4 4h16v12H4zM8 20h8M12 16v4M8 8h8M8 11h5"/>
            @break
        @case('responsible')
            <circle cx="10" cy="8" r="3"/><path d="M3.5 20c.5-3.5 2.75-5.5 6.5-5.5 1.2 0 2.25.2 3.15.65M16 17l1.5 1.5L21 15"/>
            @break
        @case('reports')
            <path d="M4 20V10M10 20V4M16 20v-7M3 20h18"/>
            @break
        @case('administration')
            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-2.1 2.1-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51v.1h-3v-.1a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06-2.1-2.1.06-.06A1.65 1.65 0 0 0 7.16 15a1.65 1.65 0 0 0-1.51-1H5.5v-3h.15a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06 2.1-2.1.06.06a1.65 1.65 0 0 0 1.82.33 1.65 1.65 0 0 0 1-1.51V4.8h3v.1a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06 2.1 2.1-.06.06A1.65 1.65 0 0 0 19.4 10a1.65 1.65 0 0 0 1.51 1h.09v3h-.09A1.65 1.65 0 0 0 19.4 15z"/>
            @break
        @case('sign-out')
            <path d="M10 4H5v16h5M14 8l4 4-4 4M8 12h10"/>
            @break
        @default
            <path d="M4 5h16v14H4zM8 9h8M8 13h5"/>
    @endswitch
</svg>

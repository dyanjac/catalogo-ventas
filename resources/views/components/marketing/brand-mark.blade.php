@props(['id'])

<svg class="marketing-brand__mark" viewBox="0 0 44 44" fill="none" aria-hidden="true" focusable="false">
    <rect width="44" height="44" rx="13" fill="url(#brand-gradient-{{ $id }}" />
    <path d="M10 29V17L17 24L23 16L34 27" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
    <circle cx="34" cy="16" r="3" fill="#8BEAF4" />
    <defs>
        <linearGradient id="brand-gradient-{{ $id }}" x1="2" y1="2" x2="42" y2="42" gradientUnits="userSpaceOnUse">
            <stop stop-color="#1647AA" />
            <stop offset="1" stop-color="#097BBA" />
        </linearGradient>
    </defs>
</svg>

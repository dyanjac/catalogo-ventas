@props(['name'])

<div class="marketing-card__icon" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        @switch($name)
            @case('sales')
                <path d="M3 17.5 9 11l4 3 8-8" /><path d="M16 6h5v5" /><path d="M3 21h18" />
                @break
            @case('inventory')
                <path d="M3 8.5 12 4l9 4.5v10L12 23l-9-4.5z" /><path d="m3 8.5 9 4.5 9-4.5M12 13v10" /><path d="m7.5 6.25 9 4.5" />
                @break
            @case('billing')
                <path d="M6 3h9l3 3v15l-3-1.5L12 21l-3-1.5L6 21z" /><path d="M14 3v4h4M9 11h6M9 15h6" />
                @break
            @case('commerce')
                <path d="M3 10h18l-1.5-6h-15zM5 10v10h14V10M9 20v-6h6v6" /><path d="M3 10c0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0" />
                @break
        @endswitch
    </svg>
</div>

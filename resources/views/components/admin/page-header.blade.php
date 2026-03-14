@props([
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->merge(['class' => 'd-flex justify-content-between align-items-center mb-4']) }}>
    <div>
        <div class="admin-page-header__eyebrow">Vista operativa</div>
        <h1 class="admin-page-header__title mb-0">{{ $title }}</h1>
        @if($description)
            <p class="admin-page-header__description mb-0">{{ $description }}</p>
        @endif
    </div>

    @if(isset($actions) || ($actionLabel && $actionHref))
        <div class="d-flex flex-wrap gap-2">
            @isset($actions)
                {{ $actions }}
            @endisset

            @if($actionLabel && $actionHref)
                <flux:button href="{{ $actionHref }}" variant="primary">{{ $actionLabel }}</flux:button>
            @endif
        </div>
    @endif
</div>

@props([
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->merge(['class' => 'd-flex justify-content-between align-items-center mb-4']) }}>
    <div>
        <h1 class="text-primary mb-0">{{ $title }}</h1>
        @if($description)
            <p class="text-muted mb-0">{{ $description }}</p>
        @endif
    </div>

    @if(isset($actions) || ($actionLabel && $actionHref))
        <div class="d-flex flex-wrap gap-2">
            @isset($actions)
                {{ $actions }}
            @endisset

            @if($actionLabel && $actionHref)
                <a href="{{ $actionHref }}" class="btn btn-primary rounded-pill px-4">{{ $actionLabel }}</a>
            @endif
        </div>
    @endif
</div>

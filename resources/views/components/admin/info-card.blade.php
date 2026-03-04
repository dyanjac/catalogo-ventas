@props([
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'card border border-secondary rounded-3']) }}>
    @if($title)
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h3 class="card-title text-primary mb-0">{{ $title }}</h3>
        </div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
</div>

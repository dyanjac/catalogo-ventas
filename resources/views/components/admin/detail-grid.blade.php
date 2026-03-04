@props([
    'items' => [],
    'columns' => 'col-md-3',
])

<div {{ $attributes->merge(['class' => 'row g-3']) }}>
    @foreach($items as $item)
        <div class="{{ $item['class'] ?? $columns }}">
            <div class="text-muted small">{{ $item['label'] ?? '' }}</div>
            <div class="fw-semibold">{{ $item['value'] ?? '-' }}</div>
        </div>
    @endforeach

    {{ $slot }}
</div>

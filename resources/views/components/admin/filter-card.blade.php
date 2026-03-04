<div {{ $attributes->merge(['class' => 'card border border-secondary rounded-3 mb-4']) }}>
    <div class="card-body">
        {{ $slot }}
    </div>
</div>

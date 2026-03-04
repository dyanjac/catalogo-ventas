@props([
    'title' => null,
    'action',
    'method' => 'POST',
    'submitLabel' => 'Guardar',
    'cancelHref' => null,
    'cancelLabel' => 'Volver',
    'enctype' => null,
])

<form
    method="POST"
    action="{{ $action }}"
    {{ $enctype ? "enctype={$enctype}" : '' }}
    {{ $attributes->merge(['class' => 'card border border-secondary rounded-3']) }}
>
    @csrf
    @if(!in_array(strtoupper($method), ['GET', 'POST'], true))
        @method($method)
    @endif

    @if($title)
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h3 class="card-title text-primary mb-0">{{ $title }}</h3>
        </div>
    @endif

    <div class="card-body">
        {{ $slot }}
    </div>

    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        @if($cancelHref)
            <a href="{{ $cancelHref }}" class="btn btn-light border rounded-pill px-4">{{ $cancelLabel }}</a>
        @endif
        <button type="submit" class="btn btn-primary rounded-pill px-4">{{ $submitLabel }}</button>
    </div>
</form>

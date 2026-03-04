@props(['paginator'])

@if($paginator instanceof \Illuminate\Contracts\Pagination\Paginator && $paginator->hasPages())
    <div {{ $attributes->merge(['class' => 'd-flex justify-content-center mt-4']) }}>
        {{ $paginator->links('pagination::bootstrap-4') }}
    </div>
@endif

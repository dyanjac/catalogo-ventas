@extends('layouts.app')

@section('title', $product->name)

@section('content')
<div class="container-fluid py-5 mt-5">
    <div class="container py-5">
        <div class="row g-4 mb-5">
            <div class="col-lg-8 col-xl-9">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="border rounded">
                            <img
                                src="{{ $product->primary_image_path ? asset('storage/' . $product->primary_image_path) : asset('img/hero-img-1.png') }}"
                                class="img-fluid rounded"
                                alt="{{ $product->name }}"
                            >
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h4 class="fw-bold mb-3">{{ $product->name }}</h4>
                        <p class="mb-2">Categoría: {{ $product->category?->name ?? '-' }}</p>
                        <p class="mb-2">Unidad: {{ $product->unitMeasure?->name ?? '-' }}</p>
                        <p class="mb-2">SKU: {{ $product->sku ?? '-' }}</p>
                        <h5 class="fw-bold mb-3">S/ {{ number_format((float) ($product->display_price ?? 0), 2) }}</h5>
                        <p class="mb-3">{{ $product->description ?: 'Producto disponible para pedido inmediato.' }}</p>
                        <p class="mb-4">
                            Stock: {{ $product->stock }}
                            @if($product->stock <= $product->min_stock)
                                <span class="badge bg-danger ms-2">Stock bajo</span>
                            @endif
                        </p>
                        <div class="d-flex gap-2 flex-wrap">
                            <form method="POST" action="{{ route('cart.add', $product->id) }}">
                                @csrf
                                <button type="submit" class="btn border border-secondary rounded-pill px-4 py-2 text-primary">
                                    <i class="fa fa-shopping-bag me-2 text-primary"></i> Agregar al carrito
                                </button>
                            </form>
                            <a href="{{ route('products.index') }}" class="btn btn-light border rounded-pill px-4 py-2">Volver a productos</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-xl-3">
                <div class="card border border-secondary rounded-3">
                    <div class="card-body">
                        <h5 class="text-primary">Ficha rápida</h5>
                        <p class="mb-1"><strong>Afectación:</strong> {{ $product->tax_affectation }}</p>
                        <p class="mb-1"><strong>Precio mayor:</strong> S/ {{ number_format((float) ($product->wholesale_price ?? 0), 2) }}</p>
                        <p class="mb-1"><strong>Precio promedio:</strong> S/ {{ number_format((float) ($product->average_price ?? 0), 2) }}</p>
                        <p class="mb-0"><strong>Usa serie:</strong> {{ $product->uses_series ? 'Sí' : 'No' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

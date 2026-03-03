@extends('layouts.admin')

@section('title', 'Detalle producto')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary mb-0">{{ $product->name }}</h1>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-primary rounded-pill px-4">Editar</a>
                <a href="{{ route('admin.products.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
            </div>
        </div>

            <div class="card border border-secondary rounded-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <strong>Imagen principal:</strong><br>
                        <img
                            src="{{ $product->primary_image_path ? asset('storage/' . $product->primary_image_path) : asset('img/hero-img-1.png') }}"
                            alt="{{ $product->name }}"
                            class="img-fluid rounded border mt-2"
                            style="max-height: 240px; object-fit: contain;"
                        >
                    </div>
                    <div class="col-md-4"><strong>SKU:</strong> {{ $product->sku ?? '-' }}</div>
                    <div class="col-md-4"><strong>Categoría:</strong> {{ $product->category?->name ?? '-' }}</div>
                    <div class="col-md-4"><strong>Unidad:</strong> {{ $product->unitMeasure?->name ?? '-' }}</div>
                    <div class="col-md-3"><strong>Precio compra:</strong> S/ {{ number_format((float) ($product->purchase_price ?? 0), 2) }}</div>
                    <div class="col-md-3"><strong>Precio venta:</strong> S/ {{ number_format((float) ($product->sale_price ?? 0), 2) }}</div>
                    <div class="col-md-3"><strong>Precio mayor:</strong> S/ {{ number_format((float) ($product->wholesale_price ?? 0), 2) }}</div>
                    <div class="col-md-3"><strong>Precio promedio:</strong> S/ {{ number_format((float) ($product->average_price ?? 0), 2) }}</div>
                    <div class="col-md-3"><strong>Stock:</strong> {{ $product->stock }}</div>
                    <div class="col-md-3"><strong>Stock mínimo:</strong> {{ $product->min_stock }}</div>
                    <div class="col-md-3"><strong>Afectación:</strong> {{ $product->tax_affectation }}</div>
                    <div class="col-md-3"><strong>Activo:</strong> {{ $product->is_active ? 'Sí' : 'No' }}</div>
                    <div class="col-md-3"><strong>Usa serie:</strong> {{ $product->uses_series ? 'Sí' : 'No' }}</div>
                    <div class="col-md-3"><strong>Cuenta:</strong> {{ $product->account ?? '-' }}</div>
                    <div class="col-12"><strong>Descripción:</strong> {{ $product->description ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


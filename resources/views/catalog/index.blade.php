@extends('layouts.app')

@section('title', 'Catálogo')

@section('content')
@php
    $sellerPhone = preg_replace('/\D+/', '', (string) env('CELULAR_VENDEDOR1', ''));
@endphp
<div class="container-fluid fruite py-5">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Catálogo de Productos</h1>
            <a href="{{ route('admin.products.index') }}" class="btn btn-light border rounded-pill px-4">Administrar</a>
        </div>

        @include('partials.flash')

        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-5">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control p-3" placeholder="Buscar productos">
            </div>
            <div class="col-md-4">
                <select name="category_id" class="form-select p-3">
                    <option value="">Todas las categorías</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                            {{ $category->name }} ({{ $category->products_count }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-grid">
                <button class="btn btn-primary">Filtrar</button>
            </div>
        </form>

        <div class="row g-4 justify-content-center">
            @forelse($products as $product)
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="rounded position-relative fruite-item h-100">
                        <div class="fruite-img">
                            <img
                                src="{{ $product->primary_image_path ? asset('storage/' . $product->primary_image_path) : asset('img/hero-img-1.png') }}"
                                class="img-fluid w-100 rounded-top"
                                alt="{{ $product->name }}"
                            >
                        </div>
                        <div class="text-white bg-secondary px-3 py-1 rounded position-absolute" style="top: 10px; left: 10px;">
                            {{ $product->unitMeasure?->name ?? 'UNIDAD' }}
                        </div>
                        <div class="p-4 border border-secondary border-top-0 rounded-bottom">
                            <h5>{{ $product->name }}</h5>
                            <p class="mb-1">{{ $product->category?->name ?? '-' }}</p>
                            <p class="text-dark fs-5 fw-bold mb-2">S/ {{ number_format((float) ($product->display_price ?? 0), 2) }}</p>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <a href="{{ route('catalog.show', $product) }}" class="btn border border-secondary rounded-pill px-3 text-primary">
                                    Ver detalle
                                </a>
                                <div class="input-group input-group-sm" style="max-width: 115px;">
                                    <span class="input-group-text">Cant.</span>
                                    <input
                                        id="qty-{{ $product->id }}"
                                        type="number"
                                        name="quantity"
                                        min="1"
                                        value="1"
                                        class="form-control"
                                    >
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <form method="POST" action="{{ route('cart.add', $product) }}" class="m-0">
                                    @csrf
                                    <input type="hidden" name="quantity" id="add-qty-{{ $product->id }}" value="1">
                                    <button type="submit" class="btn border border-secondary rounded-pill px-3 text-primary" onclick="syncQtyForCart({{ $product->id }})">
                                        <i class="fa fa-shopping-bag me-1 text-primary"></i> Agregar
                                    </button>
                                </form>
                                <button
                                    type="button"
                                    class="btn border border-success rounded-pill px-3 text-success"
                                    title="Pedir por WhatsApp"
                                    onclick="openProductWhatsApp({{ $product->id }}, @js($product->sku ?? ('ID-' . $product->id)), @js($product->name))"
                                >
                                    <i class="fab fa-whatsapp me-1"></i> Cotizar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light border">No hay productos disponibles.</div>
                </div>
            @endforelse
        </div>

        <div class="d-flex justify-content-center mt-5">
            {{ $products->links() }}
        </div>
    </div>
</div>
<script>
    function normalizeQty(input) {
        const raw = parseInt(input?.value ?? '1', 10);
        return Number.isFinite(raw) && raw > 0 ? raw : 1;
    }

    function syncQtyForCart(productId) {
        const qtyInput = document.getElementById(`qty-${productId}`);
        const hiddenInput = document.getElementById(`add-qty-${productId}`);
        hiddenInput.value = normalizeQty(qtyInput);
    }

    function openProductWhatsApp(productId, productCode, productName) {
        const phone = @js($sellerPhone);

        if (!phone) {
            alert('No se ha configurado CELULAR_VENDEDOR1 en el entorno.');
            return;
        }

        const qtyInput = document.getElementById(`qty-${productId}`);
        const qty = normalizeQty(qtyInput);
        const message = [
            'Hola, deseo cotizar este producto.',
            `Codigo: ${productCode}`,
            `Producto: ${productName}`,
            `Cantidad: ${qty}`,
        ].join('\n');

        window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
    }
</script>
@endsection

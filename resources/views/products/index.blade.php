@extends('layouts.app')

@section('title', 'Productos')

@section('content')
@php
    $sellerPhone = $commerce['mobile_digits'] ?: preg_replace('/\D+/', '', (string) env('CELULAR_VENDEDOR1', ''));
    $currentCategory = $category ?? null;
@endphp

<section class="container-fluid py-5 mt-5 mp-shell">
    <div class="container py-4">
        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Linea comercial</span>
                <h1>{{ $currentCategory?->name ? 'Productos de ' . $currentCategory->name : 'Productos para abastecimiento diario' }}</h1>
                <p>Vista comercial optimizada para mostrar precios, stock y acceso directo a cotizacion o compra.</p>
            </div>
            <a href="{{ route('catalog.index') }}" class="btn btn-light border rounded-pill px-4">Ir al catalogo</a>
        </div>

        @include('partials.flash')

        <div class="row g-4">
            <div class="col-lg-3">
                <div class="mp-category-panel">
                    <span class="mp-kicker">Categorias</span>
                    <h4 class="mb-3">Explora rapido</h4>
                    <div class="mp-mini-list">
                        @foreach($categories as $listedCategory)
                            <a href="{{ route('categories.show', $listedCategory->slug) }}" class="mp-mini-item">
                                <div class="d-flex align-items-center justify-content-center rounded-circle bg-secondary text-white" style="width: 56px; height: 56px;">
                                    <i class="fa fa-box"></i>
                                </div>
                                <div>
                                    <strong>{{ $listedCategory->name }}</strong>
                                    <span>{{ $listedCategory->products_count ?? $listedCategory->products()->count() }} productos</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="mp-info-strip mb-4">
                    <div class="mp-info-chip"><i class="fa fa-sack-dollar"></i><span>Precios visibles por unidad</span></div>
                    <div class="mp-info-chip"><i class="fa fa-layer-group"></i><span>Compra por volumen o detalle</span></div>
                    <div class="mp-info-chip"><i class="fab fa-whatsapp"></i><span>WhatsApp directo a ventas</span></div>
                </div>

                <div class="row g-4">
                    @forelse($products as $product)
                        <div class="col-md-6 col-xl-4">
                            @include('partials.product-card', ['product' => $product, 'context' => 'products'])
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="mp-empty-state">
                                <h3>No hay productos para mostrar</h3>
                                <p>Agrega productos activos o revisa otra categoria.</p>
                            </div>
                        </div>
                    @endforelse
                </div>

                <div class="d-flex justify-content-center mt-5">
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    function normalizeQtyProducts(input) {
        const raw = parseInt(input?.value ?? '1', 10);
        return Number.isFinite(raw) && raw > 0 ? raw : 1;
    }

    function syncProductQty(context, productId) {
        const qtyInput = document.getElementById(`${context}-qty-${productId}`);
        const hiddenInput = document.getElementById(`${context}-add-qty-${productId}`);

        if (hiddenInput) {
            hiddenInput.value = normalizeQtyProducts(qtyInput);
        }
    }

    function openProductWhatsApp(context, productId, productCode, productName) {
        const phone = @js($sellerPhone);

        if (!phone) {
            alert('No se ha configurado el celular comercial para WhatsApp.');
            return;
        }

        const qtyInput = document.getElementById(`${context}-qty-${productId}`);
        const qty = normalizeQtyProducts(qtyInput);
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

@extends('layouts.app')

@section('title', $product->name)

@section('content')
@php
    $sellerPhone = $commerce['mobile_digits'] ?: preg_replace('/\D+/', '', (string) env('CELULAR_VENDEDOR1', ''));
    $mainImage = $product->primary_image_path ? asset('storage/' . $product->primary_image_path) : asset('img/hero-img-1.png');
    $gallery = $product->images->isNotEmpty() ? $product->images : collect([(object) ['path' => $product->primary_image_path]]);
@endphp
<section class="container-fluid py-5 mt-5 mp-shell">
    <div class="container py-4">
        <div class="mp-breadcrumb mb-4">
            <a href="{{ route('home') }}">Inicio</a>
            <span>/</span>
            <a href="{{ route('catalog.index') }}">Catalogo</a>
            <span>/</span>
            <strong>{{ $product->name }}</strong>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <div class="mp-detail-gallery">
                    <div class="mp-detail-main">
                        <img src="{{ $mainImage }}" alt="{{ $product->name }}" class="img-fluid w-100">
                    </div>
                    <div class="row g-3 mt-1">
                        @foreach($gallery->take(4) as $image)
                            <div class="col-3">
                                <div class="mp-detail-thumb">
                                    <img
                                        src="{{ !empty($image->path) ? asset('storage/' . $image->path) : asset('img/hero-img-1.png') }}"
                                        alt="{{ $product->name }}"
                                        class="img-fluid w-100"
                                    >
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="mp-detail-panel">
                    <span class="mp-kicker">{{ $product->category?->name ?? 'Categoria' }}</span>
                    <h1 class="mp-detail-title">{{ $product->name }}</h1>
                    <div class="mp-detail-meta">
                        <span>SKU: {{ $product->sku ?? 'AUTOGENERADO' }}</span>
                        <span>Unidad: {{ $product->unitMeasure?->name ?? 'UNIDAD' }}</span>
                        <span>{{ $product->tax_affectation }}</span>
                    </div>

                    <div class="mp-detail-price-wrap">
                        <div class="mp-detail-price">S/ {{ number_format((float) ($product->display_price ?? 0), 2) }}</div>
                        <div class="mp-detail-subprice">
                            @if($product->wholesale_price)
                                Precio mayor: S/ {{ number_format((float) $product->wholesale_price, 2) }}
                            @else
                                Cotiza volumen por WhatsApp
                            @endif
                        </div>
                    </div>

                    <p class="mp-detail-copy">
                        {{ $product->description ?: 'Producto orientado a negocios de consumo masivo, abastecimiento continuo y reposicion rapida.' }}
                    </p>

                    <div class="mp-spec-grid">
                        <div><span>Stock</span><strong>{{ $product->stock }}</strong></div>
                        <div><span>Stock minimo</span><strong>{{ $product->min_stock }}</strong></div>
                        <div><span>Usa serie</span><strong>{{ $product->uses_series ? 'Si' : 'No' }}</strong></div>
                        <div><span>Cuenta</span><strong>{{ $product->account ?: 'Sin cuenta' }}</strong></div>
                    </div>

                    <div class="mp-detail-actions">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">Cantidad</span>
                            <input id="detail-qty-{{ $product->id }}" type="number" min="1" value="1" class="form-control">
                        </div>

                        <form method="POST" action="{{ route('cart.add', $product->id) }}">
                            @csrf
                            <input type="hidden" name="quantity" id="detail-add-qty-{{ $product->id }}" value="1">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill w-100" onclick="syncDetailQty({{ $product->id }})">
                                <i class="fa fa-shopping-bag me-2"></i>Agregar al carrito
                            </button>
                        </form>

                        <button
                            type="button"
                            class="btn btn-light border border-success btn-lg rounded-pill w-100 text-success"
                            onclick="openDetailWhatsApp({{ $product->id }}, @js($product->sku ?? ('ID-' . $product->id)), @js($product->name))"
                        >
                            <i class="fab fa-whatsapp me-2"></i>Cotizar por WhatsApp
                        </button>

                        <a href="{{ route('catalog.index') }}" class="btn btn-light border btn-lg rounded-pill w-100">
                            Volver al catalogo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    function normalizeDetailQty(input) {
        const raw = parseInt(input?.value ?? '1', 10);
        return Number.isFinite(raw) && raw > 0 ? raw : 1;
    }

    function syncDetailQty(productId) {
        const qtyInput = document.getElementById(`detail-qty-${productId}`);
        const hiddenInput = document.getElementById(`detail-add-qty-${productId}`);

        if (hiddenInput) {
            hiddenInput.value = normalizeDetailQty(qtyInput);
        }
    }

    function openDetailWhatsApp(productId, productCode, productName) {
        const phone = @js($sellerPhone);

        if (!phone) {
            alert('No se ha configurado el celular comercial para WhatsApp.');
            return;
        }

        const qtyInput = document.getElementById(`detail-qty-${productId}`);
        const qty = normalizeDetailQty(qtyInput);
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

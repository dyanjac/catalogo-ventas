@php
    $context = $context ?? 'catalog';
    $quantityId = $context . '-qty-' . $product->id;
    $hiddenQuantityId = $context . '-add-qty-' . $product->id;
    $primaryImage = $product->primary_image_path ? asset('storage/' . $product->primary_image_path) : asset('img/hero-img-1.png');
    $unitName = $product->unitMeasure?->name ?? 'UNIDAD';
    $categoryName = $product->category?->name ?? 'Sin categoria';
    $isLowStock = $product->stock <= $product->min_stock;
@endphp

<article class="mp-product-card h-100">
    <div class="mp-product-media">
        <img src="{{ $primaryImage }}" class="img-fluid w-100" alt="{{ $product->name }}">
        <div class="mp-product-badges">
            <span class="mp-badge mp-badge-category">{{ $categoryName }}</span>
            <span class="mp-badge mp-badge-unit">{{ $unitName }}</span>
        </div>
    </div>

    <div class="mp-product-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
                <h5 class="mp-product-title">{{ $product->name }}</h5>
                <p class="mp-product-sku mb-0">SKU: {{ $product->sku ?? 'AUTOGENERADO' }}</p>
            </div>
            @if($isLowStock)
                <span class="badge bg-danger">Stock bajo</span>
            @endif
        </div>

        <p class="mp-product-copy">{{ \Illuminate\Support\Str::limit($product->description ?? 'Ideal para pedidos frecuentes y abastecimiento de negocio.', 96) }}</p>

        <div class="mp-price-row">
            <div>
                <span class="mp-price-label">Precio venta</span>
                <div class="mp-price-value">S/ {{ number_format((float) ($product->display_price ?? 0), 2) }}</div>
            </div>
            <div class="text-end">
                <span class="mp-price-label">Stock</span>
                <div class="mp-stock-value">{{ $product->stock }}</div>
            </div>
        </div>

        <div class="mp-product-controls">
            <a href="{{ route('catalog.show', $product) }}" class="btn btn-light border rounded-pill px-3">
                Ver detalle
            </a>
            <div class="input-group input-group-sm mp-qty-group">
                <span class="input-group-text">Cant.</span>
                <input id="{{ $quantityId }}" type="number" min="1" value="1" class="form-control">
            </div>
        </div>

        <div class="mp-product-actions">
            <form method="POST" action="{{ route('cart.add', $product->id) }}" class="m-0">
                @csrf
                <input type="hidden" name="quantity" id="{{ $hiddenQuantityId }}" value="1">
                <button
                    type="submit"
                    class="btn btn-primary rounded-pill px-3 w-100"
                    onclick="syncProductQty('{{ $context }}', {{ $product->id }})"
                >
                    <i class="fa fa-shopping-bag me-2"></i>Agregar
                </button>
            </form>
            <button
                type="button"
                class="btn btn-light border border-success rounded-pill px-3 text-success w-100"
                onclick="openProductWhatsApp('{{ $context }}', {{ $product->id }}, @js($product->sku ?? ('ID-' . $product->id)), @js($product->name))"
            >
                <i class="fab fa-whatsapp me-2"></i>Cotizar
            </button>
        </div>
    </div>
</article>

@extends('layouts.app-home')
@section('title','Inicio')
@section('content')
@php
    $sellerPhone = $commerce['mobile_digits'] ?: preg_replace('/\D+/', '', (string) env('CELULAR_VENDEDOR1', ''));
@endphp

<section class="container-fluid hero-header mp-hero-surface">
    <div class="container py-5">
        <div class="row g-5 align-items-center">
            <div class="col-lg-7">
                <span class="mp-kicker">Abastecimiento para panaderias, bodegas y negocios</span>
                <h1 class="mp-hero-title">Insumos de alta rotacion con imagen profesional y pedido inmediato</h1>
                <p class="mp-hero-copy">
                    Compra harina, arroz, azucar, manteca y otros productos esenciales con una experiencia clara, moderna y enfocada en conversion.
                </p>
                <div class="mp-hero-actions">
                    <a href="{{ route('catalog.index') }}" class="btn btn-primary btn-lg rounded-pill px-5">Ver catalogo</a>
                    <a href="{{ route('contacto.index') }}" class="btn btn-light btn-lg border rounded-pill px-5">Hablar con ventas</a>
                </div>
                <div class="mp-info-strip mt-4">
                    <div class="mp-info-chip"><i class="fa fa-store"></i><span>Precios para negocio</span></div>
                    <div class="mp-info-chip"><i class="fa fa-truck"></i><span>Entrega coordinada</span></div>
                    <div class="mp-info-chip"><i class="fab fa-whatsapp"></i><span>Cotizacion rapida</span></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="mp-hero-panel">
                    <div class="mp-hero-panel-card">
                        <span class="mp-kicker">Producto estrella</span>
                        @if($featured->isNotEmpty())
                            <h3>{{ $featured->first()->name }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit($featured->first()->description ?? 'Ideal para reposicion continua de negocios.', 90) }}</p>
                            <div class="mp-detail-price mb-3">S/ {{ number_format((float) ($featured->first()->display_price ?? 0), 2) }}</div>
                            <a href="{{ route('catalog.show', $featured->first()) }}" class="btn btn-primary rounded-pill px-4">Comprar ahora</a>
                        @else
                            <h3>Catalogo listo para vender</h3>
                            <p>Agrega productos destacados y utiliza esta portada para impulsar conversion.</p>
                        @endif
                    </div>
                    <div class="mp-hero-metrics">
                        <div><strong>{{ $categories->count() }}</strong><span>Categorias</span></div>
                        <div><strong>{{ $featured->count() }}</strong><span>Destacados</span></div>
                        <div><strong>{{ $bestPrices->count() }}</strong><span>Ofertas</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container-fluid py-5 mp-shell">
    <div class="container py-4">
        <div class="row g-4 mb-5">
            <div class="col-md-6 col-lg-3">
                <div class="featurs-item text-center rounded bg-light p-4 mp-feature-card">
                    <div class="featurs-icon btn-square rounded-circle bg-secondary mb-5 mx-auto">
                        <i class="fas fa-car-side fa-3x text-white"></i>
                    </div>
                    <div class="featurs-content text-center">
                        <h5>Entrega agil</h5>
                        <p class="mb-0">Reposicion rapida para compras recurrentes.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="featurs-item text-center rounded bg-light p-4 mp-feature-card">
                    <div class="featurs-icon btn-square rounded-circle bg-secondary mb-5 mx-auto">
                        <i class="fas fa-sack-dollar fa-3x text-white"></i>
                    </div>
                    <div class="featurs-content text-center">
                        <h5>Precio competitivo</h5>
                        <p class="mb-0">Pensado para productos de consumo masivo.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="featurs-item text-center rounded bg-light p-4 mp-feature-card">
                    <div class="featurs-icon btn-square rounded-circle bg-secondary mb-5 mx-auto">
                        <i class="fas fa-box-open fa-3x text-white"></i>
                    </div>
                    <div class="featurs-content text-center">
                        <h5>Stock visible</h5>
                        <p class="mb-0">Decisiones rapidas con disponibilidad actual.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="featurs-item text-center rounded bg-light p-4 mp-feature-card">
                    <div class="featurs-icon btn-square rounded-circle bg-secondary mb-5 mx-auto">
                        <i class="fa fa-phone-alt fa-3x text-white"></i>
                    </div>
                    <div class="featurs-content text-center">
                        <h5>Venta asistida</h5>
                        <p class="mb-0">Cotiza por WhatsApp desde cada producto.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Destacados</span>
                <h2>Productos preparados para vender mas</h2>
                <p>Presentacion moderna con foco en precio, categoria, stock y accion de compra inmediata.</p>
            </div>
            <a href="{{ route('catalog.index') }}" class="btn btn-light border rounded-pill px-4">Ver todo</a>
        </div>

        <div class="row g-4 mb-5">
            @foreach($featured as $product)
                <div class="col-md-6 col-lg-4 col-xl-3">
                    @include('partials.product-card', ['product' => $product, 'context' => 'homefeatured'])
                </div>
            @endforeach
        </div>

        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Categorias activas</span>
                <h2>Explora por tipo de producto</h2>
            </div>
        </div>

        <div class="row g-4 mb-5">
            @foreach($homeGroups as $group)
                <div class="col-md-6 col-xl-4">
                    <div class="mp-category-panel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="mb-1">{{ $group->name }}</h4>
                                <p class="mb-0">{{ $group->products->count() }} productos visibles</p>
                            </div>
                            <a href="{{ route('catalog.index', ['category_id' => $group->id]) }}" class="btn btn-sm btn-light border rounded-pill px-3">Ver mas</a>
                        </div>
                        <div class="mp-mini-list">
                            @foreach($group->products->take(3) as $product)
                                <a href="{{ route('catalog.show', $product) }}" class="mp-mini-item">
                                    <img src="{{ $product->primary_image_path ? asset('storage/' . $product->primary_image_path) : asset('img/hero-img-1.png') }}" alt="{{ $product->name }}">
                                    <div>
                                        <strong>{{ $product->name }}</strong>
                                        <span>S/ {{ number_format((float) ($product->display_price ?? 0), 2) }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Mejor precio</span>
                <h2>Oportunidades para compra inteligente</h2>
            </div>
        </div>

        <div class="row g-4">
            @foreach($bestPrices->take(4) as $product)
                <div class="col-md-6 col-lg-3">
                    @include('partials.product-card', ['product' => $product, 'context' => 'homebest'])
                </div>
            @endforeach
        </div>
    </div>
</section>

<script>
    function normalizeQty(input) {
        const raw = parseInt(input?.value ?? '1', 10);
        return Number.isFinite(raw) && raw > 0 ? raw : 1;
    }

    function syncProductQty(context, productId) {
        const qtyInput = document.getElementById(`${context}-qty-${productId}`);
        const hiddenInput = document.getElementById(`${context}-add-qty-${productId}`);

        if (hiddenInput) {
            hiddenInput.value = normalizeQty(qtyInput);
        }
    }

    function openProductWhatsApp(context, productId, productCode, productName) {
        const phone = @js($sellerPhone);

        if (!phone) {
            alert('No se ha configurado el celular comercial para WhatsApp.');
            return;
        }

        const qtyInput = document.getElementById(`${context}-qty-${productId}`);
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

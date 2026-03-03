@extends('layouts.app')

@section('title', 'Catálogo')

@section('content')
@php
    $sellerPhone = preg_replace('/\D+/', '', (string) env('CELULAR_VENDEDOR1', ''));
@endphp
<section class="container-fluid py-5 mt-5 mp-shell">
    <div class="container py-4">
        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Catalogo mayorista</span>
                <h1>Compra insumos de alta rotacion con precio claro y entrega rapida</h1>
                <p>Selecciona arroz, harina, azucar y otros productos de consumo masivo con stock visible y cotizacion inmediata.</p>
            </div>
            @auth
                @if(auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.products.index') }}" class="btn btn-light border rounded-pill px-4">Administrar</a>
                @endif
            @endauth
        </div>

        @include('partials.flash')

        <div class="mp-filter-bar mb-5">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label">Buscar producto</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-lg" placeholder="Ej. harina, arroz, azucar">
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Categoria</label>
                    <select name="category_id" class="form-select form-select-lg">
                        <option value="">Todas las categorias</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>
                                {{ $category->name }} ({{ $category->products_count }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button class="btn btn-primary btn-lg rounded-pill w-100">Filtrar</button>
                    <a href="{{ route('catalog.index') }}" class="btn btn-light border btn-lg rounded-pill">Limpiar</a>
                </div>
            </form>
        </div>

        <div class="mp-info-strip mb-5">
            <div class="mp-info-chip"><i class="fa fa-truck"></i><span>Despacho para bodegas y panaderias</span></div>
            <div class="mp-info-chip"><i class="fa fa-boxes"></i><span>Stock actualizado por producto</span></div>
            <div class="mp-info-chip"><i class="fab fa-whatsapp"></i><span>Cotiza al instante por WhatsApp</span></div>
        </div>

        <div class="row g-4">
            @forelse($products as $product)
                <div class="col-md-6 col-lg-4 col-xl-3">
                    @include('partials.product-card', ['product' => $product, 'context' => 'catalog'])
                </div>
            @empty
                <div class="col-12">
                    <div class="mp-empty-state">
                        <h3>No hay productos disponibles</h3>
                        <p>Ajusta los filtros o registra nuevos productos desde administracion.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="d-flex justify-content-center mt-5">
            {{ $products->links() }}
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
            alert('No se ha configurado CELULAR_VENDEDOR1 en el entorno.');
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

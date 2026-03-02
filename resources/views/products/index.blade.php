@extends('layouts.app')

@section('title', 'Productos')

@section('content')
@php
    $sellerPhone = preg_replace('/\D+/', '', (string) env('CELULAR_VENDEDOR1', ''));
@endphp


<!-- Fruits Shop Start-->
<div class="container-fluid fruite py-5">
    <div class="container py-5">
        <h1 class="mb-4">Precios Únicos</h1>
        <div class="row g-4">
            <div class="col-lg-12">
                <div class="row g-4">
                    <div class="col-xl-3">
                        <div class="input-group w-100 mx-auto d-flex">
                            <input type="search" class="form-control p-3" placeholder="Buscar productos" aria-describedby="search-icon-1">
                            <span id="search-icon-1" class="input-group-text p-3"><i class="fa fa-search"></i></span>
                        </div>
                    </div>
                    <div class="col-6"></div>
                    <div class="col-xl-3">
                        <div class="bg-light ps-3 py-3 rounded d-flex justify-content-between mb-4">
                            <label for="fruits">Ordenar por:</label>
                            <select id="fruits" name="fruitlist" class="border-0 form-select-sm bg-light me-3">
                                <option value="nothing">Nada</option>
                                <option value="popularity">Popularidad</option>
                                <option value="organic">Orgánico</option>
                                <option value="fantastic">Fantástico</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-3">
                        <div class="mb-3">
                            <h4>Categorías</h4>
                            <ul class="list-unstyled fruite-categorie">
                                @foreach($categories as $category)
                                    <li>
                                        <div class="d-flex justify-content-between fruite-name">
                                            <a href="{{ route('categories.show', $category->slug) }}">
                                                <i class="bi bi-clipboard2-check-fill me-2"></i>{{ $category->name }}
                                            </a>
                                            <span>({{ $category->products_count }})</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-lg-9">
                        <div class="row g-4 justify-content-center">
                            @foreach($products as $product)
                                <div class="col-md-6 col-lg-4 col-xl-4">
                                    <div class="rounded position-relative fruite-item">
                                        <div class="fruite-img">
                                            <img src="{{ $product->primary_image_path ? asset('storage/' . $product->primary_image_path) : asset('img/hero-img-1.png') }}" class="img-fluid w-100 rounded-top" alt="{{ $product->name }}">
                                        </div>
                                        <div class="text-white bg-secondary px-3 py-1 rounded position-absolute" style="top: 10px; left: 10px;">
                                         <a href="{{ route('products.show', $product->slug ) }}">
                                                {{ $product->name }}
                                            </a>
                                        </div>
                                        <div class="p-4 border border-secondary border-top-0 rounded-bottom">
                                            <h4>{{ $product->name }}</h4>
                                            <p>{{ $product->description }}</p>
                                            <p class="text-dark fs-5 fw-bold mb-2">S/ {{ number_format((float) ($product->display_price ?? 0), 2) }} / Unidad</p>
                                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                                <a href="{{ route('products.show', $product->slug) }}" class="btn border border-secondary rounded-pill px-3 text-primary">
                                                    Ver detalle
                                                </a>
                                                <div class="input-group input-group-sm" style="max-width: 115px;">
                                                    <span class="input-group-text">Cant.</span>
                                                    <input
                                                        id="qty-products-{{ $product->id }}"
                                                        type="number"
                                                        name="quantity"
                                                        min="1"
                                                        value="1"
                                                        class="form-control"
                                                    >
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center gap-2">
                                                <form method="POST" action="{{ route('cart.add', $product->id) }}" class="m-0">
                                                    @csrf
                                                    <input type="hidden" name="quantity" id="add-qty-products-{{ $product->id }}" value="1">
                                                    <button type="submit" class="btn border border-secondary rounded-pill px-3 text-primary" onclick="syncQtyForProducts({{ $product->id }})">
                                                        <i class="fa fa-shopping-bag me-2 text-primary"></i> Agregar
                                                    </button>
                                                </form>
                                                <button
                                                    type="button"
                                                    class="btn border border-success rounded-pill px-3 text-success"
                                                    title="Pedir por WhatsApp"
                                                    onclick="openProductsWhatsApp({{ $product->id }}, @js($product->sku ?? ('ID-' . $product->id)), @js($product->name))"
                                                >
                                                    <i class="fab fa-whatsapp me-1"></i> WhatsApp
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            
                            <div class="col-12">
                                <div class="pagination d-flex justify-content-center mt-5">
                                    {{ $products->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<!-- Fruits Shop End-->

<script>
    function normalizeQtyProducts(input) {
        const raw = parseInt(input?.value ?? '1', 10);
        return Number.isFinite(raw) && raw > 0 ? raw : 1;
    }

    function syncQtyForProducts(productId) {
        const qtyInput = document.getElementById(`qty-products-${productId}`);
        const hiddenInput = document.getElementById(`add-qty-products-${productId}`);
        hiddenInput.value = normalizeQtyProducts(qtyInput);
    }

    function openProductsWhatsApp(productId, productCode, productName) {
        const phone = @js($sellerPhone);

        if (!phone) {
            alert('No se ha configurado CELULAR_VENDEDOR1 en el entorno.');
            return;
        }

        const qtyInput = document.getElementById(`qty-products-${productId}`);
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

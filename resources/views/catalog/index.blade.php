@extends('layouts.app')

@section('title', 'Catálogo')

@section('content')
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
                                src="{{ $product->image ? asset('storage/' . $product->image) : asset('img/hero-img-1.png') }}"
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
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('catalog.show', $product) }}" class="btn border border-secondary rounded-pill px-3 text-primary">
                                    Ver detalle
                                </a>
                                <form method="POST" action="{{ route('cart.add', $product) }}">
                                    @csrf
                                    <button type="submit" class="btn border border-secondary rounded-pill px-3 text-primary">
                                        <i class="fa fa-shopping-bag me-1 text-primary"></i> Agregar
                                    </button>
                                </form>
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
@endsection

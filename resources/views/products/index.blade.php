@extends('layouts.app')

@section('title', 'Productos')

@section('content')


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
                                            <img src="{{ asset('storage/'.$product->image) }}" class="img-fluid w-100 rounded-top" alt="{{ $product->name }}">
                                        </div>
                                        <div class="text-white bg-secondary px-3 py-1 rounded position-absolute" style="top: 10px; left: 10px;">
                                         <a href="{{ route('products.show', $product->slug ) }}">
                                                {{ $product->name }}
                                            </a>
                                        </div>
                                        <div class="p-4 border border-secondary border-top-0 rounded-bottom">
                                            <h4>{{ $product->name }}</h4>
                                            <p>{{ $product->description }}</p>
                                            <div class="d-flex justify-content-between flex-lg-wrap">
                                                <p class="text-dark fs-5 fw-bold mb-0">${{ number_format($product->price, 2) }} / Unidad</p>
                                                <a href="{{ route('cart.add', $product->id) }}" class="btn border border-secondary rounded-pill px-3 text-primary">
                                                    <i class="fa fa-shopping-bag me-2 text-primary"></i> Agregar al Carrito
                                                </a>
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

@endsection

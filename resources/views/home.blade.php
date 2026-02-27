@extends('layouts.app-home')
@section('title','Inicio')
@section('content')
<?php /** @include('partials.banner')
  <section class="grid">
    @foreach($featured as $p)
      <article>
        <a href="{{ route('products.show',$p) }}">
          <img src="{{ $p->image ? asset($p->image) : asset('img/placeholder.png') }}" alt="{{ $p->name }}">
          <h3>{{ $p->name }}</h3>
          <span>S/ {{ number_format($p->price,2) }}</span>
        </a>
        <form method="POST" action="{{ route('cart.add',$p) }}">
          @csrf
          <button>Agregar al carrito</button>
        </form>
      </article>
    @endforeach
  </section> **/
 ?>

            


        <!-- Hero Start -->
        <div class="container-fluid py-5 mb-5 hero-header">
            <div class="container py-5">
                <div class="row g-5 align-items-center">
                    <div class="col-md-12 col-lg-7">
                        <h4 class="mb-3 text-secondary">Cotizar Mis</h4>
                        <h1 class="mb-5 display-3 text-primary">Insumos de Panificación</h1>
                        <div class="position-relative mx-auto">
                            <input class="form-control border-2 border-secondary w-75 py-3 px-4 rounded-pill" type="number" placeholder="Search">
                            <button type="submit" class="btn btn-primary border-2 border-secondary py-3 px-4 position-absolute rounded-pill text-white h-100" style="top: 0; right: 25%;">Buscar</button>
                        </div>
                    </div>
                    <div class="col-md-12 col-lg-5">
                        <div id="carouselId" class="carousel slide position-relative" data-bs-ride="carousel">
                            <div class="carousel-inner" role="listbox">
                                <div class="carousel-item active rounded">
                                    <img src="img/hero-img-1.png" class="img-fluid w-100 h-100 bg-secondary rounded" alt="First slide">
                                    <a href="#" class="btn px-4 py-2 text-white rounded">Harinas</a>
                                </div>
                                <div class="carousel-item rounded">
                                    <img src="img/hero-img-2.jpg" class="img-fluid w-100 h-100 rounded" alt="Second slide">
                                    <a href="#" class="btn px-4 py-2 text-white rounded">Mantecas</a>
                                </div>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselId" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Anterior</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carouselId" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Siguiente</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Hero End -->


              <!-- Featurs Section Start -->
        <div class="container-fluid featurs py-5">
            <div class="container py-5">
                <div class="row g-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="featurs-item text-center rounded bg-light p-4">
                            <div class="featurs-icon btn-square rounded-circle bg-secondary mb-5 mx-auto">
                                <i class="fas fa-car-side fa-3x text-white"></i>
                            </div>
                            <div class="featurs-content text-center">
                                <h5>Free Shipping</h5>
                                <p class="mb-0">Free on order over $300</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="featurs-item text-center rounded bg-light p-4">
                            <div class="featurs-icon btn-square rounded-circle bg-secondary mb-5 mx-auto">
                                <i class="fas fa-user-shield fa-3x text-white"></i>
                            </div>
                            <div class="featurs-content text-center">
                                <h5>Security Payment</h5>
                                <p class="mb-0">100% security payment</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="featurs-item text-center rounded bg-light p-4">
                            <div class="featurs-icon btn-square rounded-circle bg-secondary mb-5 mx-auto">
                                <i class="fas fa-exchange-alt fa-3x text-white"></i>
                            </div>
                            <div class="featurs-content text-center">
                                <h5>30 Day Return</h5>
                                <p class="mb-0">30 day money guarantee</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="featurs-item text-center rounded bg-light p-4">
                            <div class="featurs-icon btn-square rounded-circle bg-secondary mb-5 mx-auto">
                                <i class="fa fa-phone-alt fa-3x text-white"></i>
                            </div>
                            <div class="featurs-content text-center">
                                <h5>24/7 Support</h5>
                                <p class="mb-0">Support every time fast</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Featurs Section End -->
        <!-- Fruits Shop Start-->
        <div class="container-fluid fruite py-5">
            <div class="container py-5">
                <div class="tab-class text-center">
                    <div class="row g-4">
                        <div class="col-lg-4 text-start">
                            <h1>Productos Destacados</h1>
                        </div>
                        <div class="col-lg-8 text-end">
                            <ul class="nav nav-pills d-inline-flex text-center mb-5 flex-wrap justify-content-end">
                                <li class="nav-item">
                                    <a class="d-flex m-2 py-2 bg-light rounded-pill active" data-bs-toggle="pill" href="#tab-featured">
                                        <span class="text-dark px-3">Mas recientes</span>
                                    </a>
                                </li>
                                @foreach($homeGroups as $group)
                                    <li class="nav-item">
                                        <a class="d-flex m-2 py-2 bg-light rounded-pill" data-bs-toggle="pill" href="#tab-cat-{{ $group->id }}">
                                            <span class="text-dark px-3">{{ $group->name }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="tab-content">
                        <div id="tab-featured" class="tab-pane fade show p-0 active">
                            <div class="row g-4">
                                @forelse($featured as $product)
                                    <div class="col-md-6 col-lg-4 col-xl-3">
                                        <div class="rounded position-relative fruite-item h-100">
                                            <div class="fruite-img">
                                                <img src="{{ $product->image ? asset('storage/' . $product->image) : asset('img/hero-img-1.png') }}" class="img-fluid w-100 rounded-top" alt="{{ $product->name }}">
                                            </div>
                                            <div class="text-white bg-secondary px-3 py-1 rounded position-absolute" style="top: 10px; left: 10px;">
                                                {{ $product->category?->name ?? 'Sin categoria' }}
                                            </div>
                                            <div class="p-4 border border-secondary border-top-0 rounded-bottom">
                                                <h4>{{ $product->name }}</h4>
                                                <p>{{ \Illuminate\Support\Str::limit($product->description ?? 'Producto disponible.', 85) }}</p>
                                                <div class="d-flex justify-content-between flex-lg-wrap gap-2">
                                                    <p class="text-dark fs-5 fw-bold mb-0">S/ {{ number_format((float) ($product->display_price ?? 0), 2) }} / Unidad</p>
                                                    <form method="POST" action="{{ route('cart.add', $product->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn border border-secondary rounded-pill px-3 text-primary">
                                                            <i class="fa fa-shopping-bag me-2 text-primary"></i> Agregar al Carrito
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12">
                                        <p class="text-muted mb-0">Aun no hay productos activos para mostrar.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        @foreach($homeGroups as $group)
                            <div id="tab-cat-{{ $group->id }}" class="tab-pane fade p-0">
                                <div class="row g-4">
                                    @forelse($group->products as $product)
                                        <div class="col-md-6 col-lg-4 col-xl-3">
                                            <div class="rounded position-relative fruite-item h-100">
                                                <div class="fruite-img">
                                                    <img src="{{ $product->image ? asset('storage/' . $product->image) : asset('img/hero-img-1.png') }}" class="img-fluid w-100 rounded-top" alt="{{ $product->name }}">
                                                </div>
                                                <div class="text-white bg-secondary px-3 py-1 rounded position-absolute" style="top: 10px; left: 10px;">
                                                    {{ $group->name }}
                                                </div>
                                                <div class="p-4 border border-secondary border-top-0 rounded-bottom">
                                                    <h4>{{ $product->name }}</h4>
                                                    <p>{{ \Illuminate\Support\Str::limit($product->description ?? 'Producto disponible.', 85) }}</p>
                                                    <div class="d-flex justify-content-between flex-lg-wrap gap-2">
                                                        <p class="text-dark fs-5 fw-bold mb-0">S/ {{ number_format((float) ($product->display_price ?? 0), 2) }} / Unidad</p>
                                                        <form method="POST" action="{{ route('cart.add', $product->id) }}">
                                                            @csrf
                                                            <button type="submit" class="btn border border-secondary rounded-pill px-3 text-primary">
                                                                <i class="fa fa-shopping-bag me-2 text-primary"></i> Agregar al Carrito
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <p class="text-muted mb-0">No hay productos activos en {{ $group->name }}.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <!-- Fruits Shop End-->



            <!-- Vesitable Shop Start-->
        <div class="container-fluid vesitable py-5">
            <div class="container py-5">
                <h1 class="mb-0">Los mejores Precios</h1>
                <div class="owl-carousel vegetable-carousel justify-content-center">
                    @forelse($bestPrices as $product)
                        <div class="border border-primary rounded position-relative vesitable-item">
                            <div class="vesitable-img">
                                <img src="{{ $product->image ? asset('storage/' . $product->image) : asset('img/hero-img-1.png') }}" class="img-fluid w-100 rounded-top" alt="{{ $product->name }}">
                            </div>
                            <div class="text-white bg-primary px-3 py-1 rounded position-absolute" style="top: 10px; right: 10px;">
                                {{ $product->category?->name ?? 'Sin categoria' }}
                            </div>
                            <div class="p-4 rounded-bottom">
                                <h4>{{ $product->name }}</h4>
                                <p>{{ \Illuminate\Support\Str::limit($product->description ?? 'Producto disponible.', 85) }}</p>
                                <div class="d-flex justify-content-between flex-lg-wrap">
                                    <p class="text-dark fs-5 fw-bold mb-0">S/ {{ number_format((float) ($product->display_price ?? 0), 2) }} / Unidad</p>
                                    <form method="POST" action="{{ route('cart.add', $product->id) }}">
                                        @csrf
                                        <button type="submit" class="btn border border-secondary rounded-pill px-3 text-primary">
                                            <i class="fa fa-shopping-bag me-2 text-primary"></i> Agregar al carrito
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="border border-primary rounded position-relative vesitable-item">
                            <div class="p-4 rounded-bottom">
                                <h4>Sin productos</h4>
                                <p>No hay productos activos para mostrar en esta seccion.</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        <!-- Vesitable Shop End -->


        <!-- Fact Start -->
        <div class="container-fluid py-5">
            <div class="container">
                <div class="bg-light p-5 rounded">
                    <div class="row g-4 justify-content-center">
                        <div class="col-md-6 col-lg-6 col-xl-3">
                            <div class="counter bg-white rounded p-5">
                                <i class="fa fa-users text-secondary"></i>
                                <h4>satisfied customers</h4>
                                <h1>1963</h1>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-6 col-xl-3">
                            <div class="counter bg-white rounded p-5">
                                <i class="fa fa-users text-secondary"></i>
                                <h4>quality of service</h4>
                                <h1>99%</h1>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-6 col-xl-3">
                            <div class="counter bg-white rounded p-5">
                                <i class="fa fa-users text-secondary"></i>
                                <h4>quality certificates</h4>
                                <h1>33</h1>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-6 col-xl-3">
                            <div class="counter bg-white rounded p-5">
                                <i class="fa fa-users text-secondary"></i>
                                <h4>Available Products</h4>
                                <h1>789</h1>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Fact Start -->


@endsection


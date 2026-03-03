@php
    $cartCount = collect(session('cart', []))->sum('quantity');
@endphp

<div id="spinner" class="show w-100 vh-100 bg-white position-fixed translate-middle top-50 start-50 d-flex align-items-center justify-content-center">
    <div class="spinner-grow text-primary" role="status"></div>
</div>

<div class="container-fluid fixed-top">
    <div class="container topbar mp-topbar d-none d-lg-block">
        <div class="d-flex justify-content-between align-items-center">
            <div class="top-info ps-2">
                <small class="me-4"><i class="fas fa-map-marker-alt me-2 text-secondary"></i><a href="#" class="text-white">Psj. Señor de los Milagros 01</a></small>
                <small><i class="fas fa-envelope me-2 text-secondary"></i><a href="#" class="text-white">inversiones@gmail.com</a></small>
            </div>
            <div class="top-link pe-2">
                <span class="text-white-50 small me-3">Soluciones para venta mayorista y abastecimiento</span>
                <a href="{{ route('contacto.index') }}" class="text-white"><small class="text-white mx-2">Cotizar</small></a>
            </div>
        </div>
    </div>

    <div class="container px-0">
        <nav class="navbar navbar-light bg-white navbar-expand-xl mp-navbar-shell">
            <a href="{{ route('home') }}" class="navbar-brand d-flex align-items-center py-0">
                <img
                    src="{{ asset('img/logo-V&V.png') }}"
                    alt="Inversiones V&V"
                    class="mp-brand-logo"
                >
            </a>
            <button class="navbar-toggler py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="fa fa-bars text-primary"></span>
            </button>
            <div class="collapse navbar-collapse bg-white" id="navbarCollapse">
                <div class="navbar-nav mx-auto mp-navbar-links">
                    <a href="{{ route('home') }}" class="nav-item nav-link {{ request()->routeIs('home') ? 'active' : '' }}">Inicio</a>
                    <a href="{{ route('catalog.index') }}" class="nav-item nav-link {{ request()->routeIs('catalog.*') ? 'active' : '' }}">Catálogo</a>
                    <a href="{{ route('nosotros.index') }}" class="nav-item nav-link {{ request()->routeIs('nosotros.index') ? 'active' : '' }}">Nosotros</a>
                    <a href="{{ route('contacto.index') }}" class="nav-item nav-link {{ request()->routeIs('contacto.index') ? 'active' : '' }}">Contacto</a>
                </div>
                <div class="d-flex align-items-center m-3 me-0 mp-nav-actions">
                    <button class="btn-search btn border border-secondary btn-md-square rounded-circle bg-white me-3" data-bs-toggle="modal" data-bs-target="#searchModal">
                        <i class="fas fa-search text-primary"></i>
                    </button>
                    <a href="{{ route('cart.view') }}" class="position-relative me-3 my-auto mp-cart-link" title="Ver carrito">
                        <i class="fa fa-shopping-bag fa-2x"></i>
                        <span class="position-absolute bg-secondary rounded-circle d-flex align-items-center justify-content-center text-dark px-1 mp-cart-count">{{ $cartCount }}</span>
                    </a>
                    @auth
                        <div class="nav-item dropdown my-auto">
                            <a href="#" class="nav-link dropdown-toggle p-0" data-bs-toggle="dropdown">
                                <i class="fas fa-user fa-2x"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end m-0 bg-white rounded-4 border-0 shadow-sm">
                                <span class="dropdown-item-text fw-semibold">{{ auth()->user()->name }}</span>
                                @if(auth()->user()->isSuperAdmin())
                                    <span class="dropdown-item-text small text-muted">Super usuario</span>
                                    <a href="{{ route('admin.orders.index') }}" class="dropdown-item">Todos los pedidos</a>
                                    <a href="{{ route('admin.customers.index') }}" class="dropdown-item">Clientes</a>
                                    <a href="{{ route('admin.products.index') }}" class="dropdown-item">Productos</a>
                                    <a href="{{ route('admin.categories.index') }}" class="dropdown-item">Categorias</a>
                                    <a href="{{ route('admin.unit-measures.index') }}" class="dropdown-item">Unidades</a>
                                    <hr class="dropdown-divider">
                                @endif
                                <a href="{{ route('orders.mine') }}" class="dropdown-item">Mis pedidos</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Cerrar sesión</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="#" class="my-auto" data-bs-toggle="modal" data-bs-target="#authModal" title="Iniciar sesión">
                            <i class="fas fa-user fa-2x"></i>
                        </a>
                    @endauth
                </div>
            </div>
        </nav>
    </div>
</div>

<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content rounded-0">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Buscar producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body d-flex align-items-center">
                <div class="input-group w-75 mx-auto d-flex">
                    <input type="search" class="form-control p-3" placeholder="Escribe harina, arroz, azucar..." aria-describedby="search-icon-1">
                    <span id="search-icon-1" class="input-group-text p-3"><i class="fa fa-search"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

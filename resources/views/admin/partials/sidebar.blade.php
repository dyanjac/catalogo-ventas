<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="{{ route('admin.dashboard') }}" class="brand-link d-flex align-items-center">
        <img src="{{ $commerce['logo_url'] }}" alt="{{ $commerce['name'] }}" class="brand-image img-circle elevation-2" style="opacity: .95; width: 34px; height: 34px; object-fit: cover;">
        <span class="brand-text font-weight-light">{{ $commerce['name'] }}</span>
    </a>

    <div class="sidebar">
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info px-2 text-white">
                <div class="d-block font-weight-bold">{{ auth()->user()->name }}</div>
                <small>Super usuario</small>
            </div>
        </div>

        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-gauge-high"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-header">GESTION COMERCIAL</li>
                <li class="nav-item">
                    <a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-receipt"></i>
                        <p>Pedidos</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.customers.index') }}" class="nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Clientes</p>
                    </a>
                </li>
                <li class="nav-header">CATALOGO</li>
                <li class="nav-item">
                    <a href="{{ route('admin.products.index') }}" class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-boxes-stacked"></i>
                        <p>Productos</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.categories.index') }}" class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Categorias</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.unit-measures.index') }}" class="nav-link {{ request()->routeIs('admin.unit-measures.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-ruler-combined"></i>
                        <p>Unidades</p>
                    </a>
                </li>
                <li class="nav-header">SITIO</li>
                <li class="nav-item">
                    <a href="{{ route('admin.settings.edit') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-building"></i>
                        <p>Comercio</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('orders.mine') }}" class="nav-link">
                        <i class="nav-icon fas fa-bag-shopping"></i>
                        <p>Mis pedidos</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('home') }}" class="nav-link">
                        <i class="nav-icon fas fa-store"></i>
                        <p>Volver a tienda</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>


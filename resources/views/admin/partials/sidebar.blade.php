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
                    <a href="{{ route('admin.sales.pos.index') }}" class="nav-link {{ request()->routeIs('admin.sales.pos.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-cash-register"></i>
                        <p>Punto de venta</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-receipt"></i>
                        <p>Pedidos</p>
                    </a>
                </li>
                <li class="nav-item {{ request()->routeIs('admin.accounting.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('admin.accounting.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-calculator"></i>
                        <p>
                            Contabilidad
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.accounting.entries.index') }}" class="nav-link {{ request()->routeIs('admin.accounting.entries.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Asientos contables</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.accounting.accounts.index') }}" class="nav-link {{ request()->routeIs('admin.accounting.accounts.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Plan de cuentas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.accounting.periods.index') }}" class="nav-link {{ request()->routeIs('admin.accounting.periods.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Periodos contables</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.accounting.cost-centers.index') }}" class="nav-link {{ request()->routeIs('admin.accounting.cost-centers.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Centros de costo</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.accounting.settings.edit') }}" class="nav-link {{ request()->routeIs('admin.accounting.settings.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Config. contable</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-header">FACTURACION ELECTRONICA</li>
                <li class="nav-item {{ request()->routeIs('admin.billing.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('admin.billing.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-file-invoice-dollar"></i>
                        <p>
                            Facturacion electronica
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('admin.billing.documents.index') }}" class="nav-link {{ request()->routeIs('admin.billing.documents.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Docs electronicos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.billing.settings.edit') }}" class="nav-link {{ request()->routeIs('admin.billing.settings.*') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Config. facturacion</p>
                            </a>
                        </li>
                    </ul>
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
                    <a href="{{ route('admin.theme.edit') }}" class="nav-link {{ request()->routeIs('admin.theme.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-palette"></i>
                        <p>Paleta Admin</p>
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


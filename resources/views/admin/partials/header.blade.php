<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <ul class="navbar-nav ml-auto align-items-center">
        <li class="nav-item d-none d-sm-inline-block mr-3 text-muted small">
            {{ now()->format('d/m/Y H:i') }}
        </li>
        <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <li class="user-header bg-primary">
                    <p>
                        {{ auth()->user()->name }}
                        <small>{{ auth()->user()->email }}</small>
                    </p>
                </li>
                <li class="user-footer d-flex justify-content-between px-3 py-2">
                    <a href="{{ route('home') }}" class="btn btn-default btn-flat">Ver tienda</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-default btn-flat">Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </li>
    </ul>
</nav>


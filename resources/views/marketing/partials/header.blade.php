<header class="marketing-header">
    <div class="marketing-container marketing-header__inner">
        <a class="marketing-brand" href="{{ route('home') }}" aria-label="{{ config('marketing.brand_name') }}: inicio">
            <x-marketing.brand-mark id="header" />
            <span>{{ config('marketing.brand_name') }}</span>
        </a>

        <nav class="marketing-nav" aria-label="Navegación principal">
            <a href="{{ route('home') }}#plataforma">Plataforma</a>
            <a href="{{ route('home') }}#capacidades">Capacidades</a>
            <a href="{{ route('home') }}#como-empezar">Cómo empezar</a>
            <a class="marketing-nav__login" href="{{ route('admin.login') }}">Ingresar</a>
            <a class="marketing-button marketing-button--small" href="{{ route('saas.register.create') }}">Crear organización</a>
        </nav>

        <details class="marketing-mobile-menu">
            <summary>
                Menú
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </summary>
            <nav aria-label="Navegación móvil">
                <a href="{{ route('home') }}#plataforma">Plataforma</a>
                <a href="{{ route('home') }}#capacidades">Capacidades</a>
                <a href="{{ route('home') }}#como-empezar">Cómo empezar</a>
                <a href="{{ route('admin.login') }}">Ingresar</a>
                <a class="marketing-button marketing-button--small" href="{{ route('saas.register.create') }}">Crear organización</a>
            </nav>
        </details>
    </div>
</header>

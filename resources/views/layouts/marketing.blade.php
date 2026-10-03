<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Gestiona ventas, inventario y facturación en una plataforma ERP para tu empresa.">
    <title>@yield('title', config('marketing.brand_name'))</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite('resources/css/marketing.css')
</head>
<body class="marketing-page">
    <a class="marketing-skip" href="#contenido">Saltar al contenido</a>

    <header class="marketing-header">
        <div class="marketing-container marketing-header__inner">
            <a class="marketing-brand" href="{{ route('home') }}" aria-label="{{ config('marketing.brand_name') }}: inicio">
                <span class="marketing-brand__mark" aria-hidden="true">M</span>
                <span>{{ config('marketing.brand_name') }}</span>
            </a>
            <nav class="marketing-nav" aria-label="Navegación principal">
                <a href="{{ route('home') }}#plataforma">Plataforma</a>
                <a href="{{ route('home') }}#capacidades">Capacidades</a>
                <a href="{{ route('admin.login') }}">Ingresar</a>
                <a class="marketing-button marketing-button--small" href="{{ route('saas.register.create') }}">Crear organización</a>
            </nav>
        </div>
    </header>

    <main id="contenido">@yield('content')</main>

    <footer class="marketing-footer">
        <div class="marketing-container marketing-footer__inner">
            <span>{{ config('marketing.brand_name') }}</span>
            <span>Una plataforma para operar y vender mejor.</span>
            <a href="{{ route('admin.login') }}">Acceso al ERP</a>
        </div>
    </footer>
</body>
</html>

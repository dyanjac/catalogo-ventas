<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Gestiona ventas, inventario y facturación en una plataforma ERP para tu empresa.">
    <title>@yield('title', config('marketing.brand_name'))</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">
    @vite('resources/css/marketing.css')
</head>
<body class="marketing-page">
    <a class="marketing-skip" href="#contenido">Saltar al contenido</a>

    @include('marketing.partials.header')

    <main id="contenido">@yield('content')</main>

    @include('marketing.partials.footer')
</body>
</html>

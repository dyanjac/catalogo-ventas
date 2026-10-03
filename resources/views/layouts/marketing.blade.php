<!doctype html>
<html lang="es">
<head>
    @php
        $seoTitle = trim($__env->yieldContent('title', config('marketing.brand_name')));
        $seoDescription = trim($__env->yieldContent('description', config('marketing.description')));
        $seoCanonical = trim($__env->yieldContent('canonical', url()->current()));
        $seoRobots = trim($__env->yieldContent('robots', 'index, follow'));
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <meta name="theme-color" content="#102f60">
    <link rel="canonical" href="{{ $seoCanonical }}">
    <meta property="og:locale" content="es_PE">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('marketing.brand_name') }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <title>{{ $seoTitle }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">
    @if(config('marketing.analytics.enabled') && filled(config('marketing.analytics.domain')))
        <script defer data-domain="{{ config('marketing.analytics.domain') }}" src="{{ config('marketing.analytics.script_url') }}"></script>
        <script>window.plausible = window.plausible || function () { (window.plausible.q = window.plausible.q || []).push(arguments); };</script>
    @endif
    @stack('head')
    @vite(['resources/css/marketing.css', 'resources/js/marketing.js'])
    @stack('styles')
</head>
<body class="marketing-page">
    <a class="marketing-skip" href="#contenido">Saltar al contenido</a>

    @include('marketing.partials.header')

    <main id="contenido" tabindex="-1">@yield('content')</main>

    @include('marketing.partials.footer')
    @stack('scripts')
</body>
</html>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel CMS')</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+3:300,400,400i,700&display=fallback">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @php
        $palette = array_merge(config('admintheme.defaults', []), $adminPalette ?? []);
    @endphp
    <style>
        .admin-shell {
            --admin-sidebar-bg: {{ $palette['sidebar_bg'] ?? '#2f3a20' }};
            --admin-sidebar-gradient-to: {{ $palette['sidebar_gradient_to'] ?? '#4f5f2f' }};
            --admin-sidebar-text: {{ $palette['sidebar_text'] ?? '#ffffff' }};
            --admin-topbar-bg: {{ $palette['topbar_bg'] ?? '#ffffff' }};
            --admin-topbar-text: {{ $palette['topbar_text'] ?? '#1f2d3d' }};
            --admin-primary-button: {{ $palette['primary_button'] ?? '#6c7f3e' }};
            --admin-primary-button-hover: {{ $palette['primary_button_hover'] ?? '#5d6e35' }};
            --admin-active-link-bg: {{ $palette['active_link_bg'] ?? '#d4a64a' }};
            --admin-active-link-text: {{ $palette['active_link_text'] ?? '#1f2d3d' }};
            --admin-card-border: {{ $palette['card_border'] ?? '#6f7d5c2e' }};
            --admin-focus-ring: {{ $palette['focus_ring'] ?? '#6c7f3e40' }};
        }
    </style>
    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed admin-shell">
<div class="wrapper">
    @include('admin.partials.header')
    @include('admin.partials.sidebar')

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>@yield('page_title', View::yieldContent('title', 'Panel CMS'))</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Panel</a></li>
                            <li class="breadcrumb-item active">@yield('page_title', View::yieldContent('title', 'Panel CMS'))</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                @include('partials.flash')
                @yield('content')
            </div>
        </section>
    </div>
</div>
@stack('scripts')
</body>
</html>

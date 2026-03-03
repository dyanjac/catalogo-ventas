<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel CMS')</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+3:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        body { font-family: 'Source Sans 3', sans-serif; }
        .content-wrapper { background: #f4f6f9; }
        .main-sidebar { background: linear-gradient(180deg, #2f3a20 0%, #4f5f2f 100%); }
        .brand-link { border-bottom: 1px solid rgba(255,255,255,.08); }
        .brand-link .brand-image { float: none; margin-left: 0; margin-right: .5rem; }
        .nav-sidebar .nav-link.active { background: #d4a64a; color: #1f2d3d; }
        .nav-sidebar .nav-link:not(.active):hover { background: rgba(255,255,255,.08); }
        .main-header.navbar { border-bottom: 1px solid #e5e7eb; }
        .content-header h1 { font-size: 1.75rem; margin: 0; }
        .small-box .icon > i { font-size: 54px; top: 18px; }
        .admin-shell .card { border-radius: 1rem; border: 1px solid rgba(111, 125, 92, .18); box-shadow: 0 12px 24px rgba(31, 45, 61, .05); }
        .admin-shell .btn-primary { background-color: #6c7f3e; border-color: #6c7f3e; }
        .admin-shell .btn-primary:hover { background-color: #5d6e35; border-color: #5d6e35; }
        .admin-shell .page-link { color: #6c7f3e; }
        .admin-shell .page-item.active .page-link { background-color: #6c7f3e; border-color: #6c7f3e; }
        .admin-shell .table thead th { border-bottom-width: 1px; }
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

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
@stack('scripts')
</body>
</html>

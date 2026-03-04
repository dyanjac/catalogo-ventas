<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel CMS')</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+3:300,400,400i,700&display=fallback">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
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

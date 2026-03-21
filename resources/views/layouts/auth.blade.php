<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', ($commerce['name'] ?? 'Acceso seguro').' | Seguridad')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @fluxAppearance
    @livewireStyles
    @php
        $palette = array_merge(config('admintheme.defaults', []), $adminPalette ?? []);
    @endphp
    <style>
        .auth-shell {
            --admin-sidebar-bg: {{ $palette['sidebar_bg'] ?? '#2f3a20' }};
            --admin-sidebar-gradient-to: {{ $palette['sidebar_gradient_to'] ?? '#4f5f2f' }};
            --admin-sidebar-text: {{ $palette['sidebar_text'] ?? '#ffffff' }};
            --admin-sidebar-group-text: {{ $palette['sidebar_group_text'] ?? ($palette['sidebar_text'] ?? '#ffffff') }};
            --admin-sidebar-group-bg: {{ $palette['sidebar_group_bg'] ?? ($palette['active_link_bg'] ?? '#d4a64a') }};
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
</head>
<body class="auth-shell">
    <main>
        @yield('content')
    </main>

    @fluxScripts
    @livewireScripts
</body>
</html>

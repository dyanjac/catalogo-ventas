<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', ($commerce['name'] ?? 'Acceso seguro').' | Seguridad')</title>
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
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
            --admin-sidebar-bg: {{ $palette['sidebar_bg'] ?? '#1E293B' }};
            --admin-sidebar-gradient-to: {{ $palette['sidebar_gradient_to'] ?? '#334155' }};
            --admin-sidebar-text: {{ $palette['sidebar_text'] ?? '#F8FAFC' }};
            --admin-sidebar-group-text: {{ $palette['sidebar_group_text'] ?? '#F8FAFC' }};
            --admin-sidebar-group-bg: {{ $palette['sidebar_group_bg'] ?? '#334155' }};
            --admin-topbar-bg: {{ $palette['topbar_bg'] ?? '#FFFFFF' }};
            --admin-topbar-text: {{ $palette['topbar_text'] ?? '#0F172A' }};
            --admin-primary-button: {{ $palette['primary_button'] ?? '#0F766E' }};
            --admin-primary-button-hover: {{ $palette['primary_button_hover'] ?? '#115E59' }};
            --admin-active-link-bg: {{ $palette['active_link_bg'] ?? '#CCFBF1' }};
            --admin-active-link-text: {{ $palette['active_link_text'] ?? '#134E4A' }};
            --admin-card-border: {{ $palette['card_border'] ?? '#CBD5E1' }};
            --admin-focus-ring: {{ $palette['focus_ring'] ?? '#0F766E' }};
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

<!doctype html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel CMS')</title>
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet" />
    @fluxAppearance
    @livewireStyles
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @php
        $palette = array_merge(config('admintheme.defaults', []), $adminPalette ?? []);
    @endphp
    <style>
        :root {
            --livewire-progress-bar-color: {{ $palette['primary_button'] ?? '#0F766E' }};
        }

        .admin-shell {
            --admin-sidebar-bg: {{ $palette['sidebar_bg'] ?? '#1E293B' }};
            --admin-sidebar-gradient-to: {{ $palette['sidebar_gradient_to'] ?? '#334155' }};
            --admin-sidebar-text: {{ $palette['sidebar_text'] ?? '#F8FAFC' }};
            --admin-sidebar-group-text: {{ $palette['sidebar_group_text'] ?? '#F8FAFC' }};
            --admin-sidebar-group-bg: {{ $palette['sidebar_group_bg'] ?? '#334155' }};
            --admin-topbar-bg: {{ $palette['topbar_bg'] ?? '#FFFFFF' }};
            --admin-topbar-text: {{ $palette['topbar_text'] ?? '#0F172A' }};
            --admin-user-menu-trigger-bg: {{ $palette['user_menu_trigger_bg'] ?? '#FFFFFF' }};
            --admin-user-menu-trigger-text: {{ $palette['user_menu_trigger_text'] ?? '#0F172A' }};
            --admin-user-menu-dropdown-bg: {{ $palette['user_menu_dropdown_bg'] ?? '#FFFFFF' }};
            --admin-user-menu-dropdown-text: {{ $palette['user_menu_dropdown_text'] ?? '#0F172A' }};
            --admin-user-menu-dropdown-hover-bg: {{ $palette['user_menu_dropdown_hover_bg'] ?? '#CCFBF1' }};
            --admin-user-menu-dropdown-hover-text: {{ $palette['user_menu_dropdown_hover_text'] ?? '#134E4A' }};
            --admin-primary-button: {{ $palette['primary_button'] ?? '#0F766E' }};
            --admin-primary-button-hover: {{ $palette['primary_button_hover'] ?? '#115E59' }};
            --admin-active-link-bg: {{ $palette['active_link_bg'] ?? '#CCFBF1' }};
            --admin-active-link-text: {{ $palette['active_link_text'] ?? '#134E4A' }};
            --admin-card-border: {{ $palette['card_border'] ?? '#CBD5E1' }};
            --admin-focus-ring: {{ $palette['focus_ring'] ?? '#0F766E' }};
        }
    </style>
    <script>
        try {
            document.documentElement.dataset.adminSidebarCollapsed = localStorage.getItem('admin-sidebar-collapsed') === 'true' ? 'true' : 'false';
        } catch (error) {
            document.documentElement.dataset.adminSidebarCollapsed = 'false';
        }
    </script>
    @stack('styles')
</head>
<body class="admin-shell min-h-full">
    <div class="admin-layout">
        @include('admin.partials.sidebar')

        <div class="admin-stage">
            @include('admin.partials.header')

            <flux:main class="admin-main">
                @include('partials.flash')
                @yield('content')
            </flux:main>
        </div>
    </div>

    @fluxScripts
    @livewireScripts
    @stack('scripts')
</body>
</html>

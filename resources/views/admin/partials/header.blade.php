@php
    $pageTitle = View::yieldContent('page_title', View::yieldContent('title', 'Panel CMS'));
    $user = auth()->user();
    $orgName = $organizationContext['organization_name'] ?? null;
    $isDemo = (bool) ($organizationContext['is_demo'] ?? false);
    $environment = strtoupper((string) ($organizationContext['environment'] ?? 'production'));
    $initials = collect(explode(' ', (string) $user?->name))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<header class="admin-topbar">
    <div class="admin-topbar__surface">
        <div class="admin-topbar__group">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-3" />

            <button
                type="button"
                class="admin-topbar__collapse d-none d-lg-inline-flex"
                data-admin-sidebar-toggle
                aria-pressed="false"
                aria-label="Ocultar menu lateral"
                title="Ocultar menu lateral"
            >
                <span class="admin-topbar__collapse-icon admin-topbar__collapse-icon--bars" aria-hidden="true">
                    <span></span>
                </span>
                <span class="admin-topbar__collapse-label" data-admin-sidebar-toggle-label>Ocultar menu</span>
            </button>

            <div class="admin-topbar__brand">
                <div class="admin-topbar__brand-mark">
                    <img src="{{ $commerce['logo_url'] }}" alt="{{ $commerce['brand_name'] }}">
                </div>
                <div class="admin-topbar__title">
                    <div class="admin-topbar__eyebrow">
                        <span class="admin-topbar__eyebrow-badge">{{ $commerce['brand_name'] }}</span>
                        @if($isDemo)
                            <span class="admin-topbar__eyebrow-badge admin-topbar__eyebrow-badge--demo">
                                Entorno {{ $environment }}
                            </span>
                        @endif
                        <span class="d-none d-md-inline">{{ now()->format('d/m/Y H:i') }}</span>
                    </div>
                    <div>
                        <h1 class="admin-topbar__heading">{{ $pageTitle }}</h1>
                        <p class="admin-topbar__subtitle">
                            {{ $commerce['tagline'] ?: $commerce['legal_name'] }}
                            @if($orgName)
                                &middot; {{ $orgName }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-topbar__actions">
            @if($commerce['support_phone_digits'])
                <flux:button
                    href="tel:{{ $commerce['support_phone_digits'] }}"
                    variant="outline"
                    icon="phone"
                    size="sm"
                    class="d-none d-xl-inline-flex"
                >
                    {{ $commerce['support_phone'] }}
                </flux:button>
            @elseif($commerce['mobile_digits'])
                <flux:button
                    href="{{ $commerce['whatsapp_url'] }}?text=Hola%2C%20necesito%20apoyo%20comercial."
                    target="_blank"
                    variant="outline"
                    icon="phone"
                    size="sm"
                    class="d-none d-xl-inline-flex"
                >
                    {{ $commerce['mobile'] }}
                </flux:button>
            @elseif($commerce['phone_digits'])
                <flux:button
                    href="tel:{{ $commerce['phone_digits'] }}"
                    variant="outline"
                    icon="phone"
                    size="sm"
                    class="d-none d-xl-inline-flex"
                >
                    {{ $commerce['phone'] }}
                </flux:button>
            @endif

            <flux:button href="{{ route('home') }}" wire:navigate.hover variant="primary" icon="shopping-bag" size="sm">
                Ver tienda
            </flux:button>

            <flux:dropdown position="bottom" align="end" class="admin-user-menu">
                <flux:profile
                    :name="$user?->name"
                    :initials="$initials !== '' ? $initials : 'AD'"
                    circle
                />

                <flux:menu class="admin-user-menu__panel">
                    <flux:menu.item icon="user-circle" class="admin-user-menu__identity">
                        {{ $user?->email }}
                    </flux:menu.item>

                    <flux:menu.item href="{{ route('admin.dashboard') }}" wire:navigate.hover icon="home">
                        Dashboard
                    </flux:menu.item>

                    <flux:menu.item href="{{ route('home') }}" wire:navigate.hover icon="shopping-bag">
                        Ver tienda
                    </flux:menu.item>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:menu.item type="submit" icon="arrow-left-start-on-rectangle" variant="danger">
                            Cerrar sesion
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    @if($isDemo)
        <div class="admin-topbar__demo-banner">
            <strong>ENTORNO DEMO</strong>
            <span>Estas operando sobre datos de demostracion{{ $orgName ? ' de ' . $orgName : '' }}.</span>
        </div>
    @endif
</header>

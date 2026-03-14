@php
    $pageTitle = View::yieldContent('page_title', View::yieldContent('title', 'Panel CMS'));
    $user = auth()->user();
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

            <div class="admin-topbar__brand">
                <div class="admin-topbar__brand-mark">
                    <img src="{{ $commerce['logo_url'] }}" alt="{{ $commerce['name'] }}">
                </div>
                <div class="admin-topbar__title">
                    <div class="admin-topbar__eyebrow">
                        <span class="admin-topbar__eyebrow-badge">Monolito modular</span>
                        <span class="d-none d-md-inline">{{ now()->format('d/m/Y H:i') }}</span>
                    </div>
                    <div>
                        <h1 class="admin-topbar__heading">{{ $pageTitle }}</h1>
                        <p class="admin-topbar__subtitle">
                            {{ $commerce['name'] }} · Panel administrativo centralizado con módulos desacoplados.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-topbar__actions">
            @if($commerce['mobile_digits'])
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

            <flux:button href="{{ route('home') }}" variant="primary" icon="shopping-bag" size="sm">
                Ver tienda
            </flux:button>

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :name="$user?->name"
                    :initials="$initials !== '' ? $initials : 'AD'"
                    circle
                />

                <flux:menu>
                    <flux:menu.item icon="user-circle">
                        {{ $user?->email }}
                    </flux:menu.item>

                    <flux:menu.item href="{{ route('admin.dashboard') }}" icon="home">
                        Dashboard
                    </flux:menu.item>

                    <flux:menu.item href="{{ route('home') }}" icon="shopping-bag">
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
</header>


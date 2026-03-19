@php
    $user = auth()->user();
    $authorization = app(\Modules\Security\Services\SecurityAuthorizationService::class);
    $modules = $authorization->modulesForNavigation($user)->keyBy('code');
    $canViewSecurityRoles = $authorization->hasPermission($user, 'security.roles.view');
    $canViewSecurityUsers = $authorization->hasPermission($user, 'security.users.view');
    $canViewSecurityBranches = $authorization->hasPermission($user, 'security.branches.view');
    $canViewSecurityAudit = $authorization->hasPermission($user, 'security.audit.view');
@endphp

<flux:sidebar sticky collapsible="mobile" class="admin-sidebar">
    <flux:sidebar.header>
        <a href="{{ route('admin.dashboard') }}" wire:navigate.hover class="admin-brand">
            <span class="admin-brand__logo-wrap">
                <img src="{{ $commerce['logo_url'] }}" alt="{{ $commerce['name'] }}" class="admin-brand__logo">
            </span>
            <span class="admin-brand__meta">
                <span class="admin-brand__title">{{ $commerce['name'] }}</span>
                <span class="admin-brand__subtitle">{{ $commerce['email'] ?: 'Operaciones comerciales' }}</span>
            </span>
        </a>
    </flux:sidebar.header>

    <div class="admin-sidebar__profile">
        <div class="admin-sidebar__profile-label">Responsable activo</div>
        <div class="admin-sidebar__profile-name">{{ $user?->name }}</div>
        <div class="admin-sidebar__profile-role">{{ $user?->email }}</div>
    </div>

    <flux:sidebar.nav>
        @if($modules->has('dashboard'))
            <flux:sidebar.item wire:navigate.hover href="{{ route('admin.dashboard') }}" icon="home" :data-current="request()->routeIs('admin.dashboard') ? 'true' : null">
                Dashboard
            </flux:sidebar.item>
        @endif

        @if($modules->has('sales') || $modules->has('pos') || $modules->has('customers'))
            <flux:sidebar.group heading="Gestion comercial">
                @if($modules->has('pos'))
                    <flux:sidebar.item wire:navigate.hover href="{{ route('admin.sales.pos.index') }}" icon="banknotes" :data-current="request()->routeIs('admin.sales.pos.*') ? 'true' : null">
                        Punto de venta
                    </flux:sidebar.item>
                @endif
                @if($modules->has('sales'))
                    <flux:sidebar.item wire:navigate.hover href="{{ route('admin.orders.index') }}" icon="receipt-percent" :data-current="request()->routeIs('admin.orders.*') ? 'true' : null">
                        Pedidos
                    </flux:sidebar.item>
                @endif
                @if($modules->has('customers'))
                    <flux:sidebar.item wire:navigate.hover href="{{ route('admin.customers.index') }}" icon="users" :data-current="request()->routeIs('admin.customers.*') ? 'true' : null">
                        Clientes
                    </flux:sidebar.item>
                @endif
            </flux:sidebar.group>
        @endif

        @if($modules->has('accounting'))
            <flux:sidebar.group heading="Contabilidad" icon="calculator" expandable :expanded="request()->routeIs('admin.accounting.*')">
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.accounting.entries.index') }}" icon="clipboard-document-list" :data-current="request()->routeIs('admin.accounting.entries.*') ? 'true' : null">Asientos contables</flux:sidebar.item>
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.accounting.accounts.index') }}" icon="square-3-stack-3d" :data-current="request()->routeIs('admin.accounting.accounts.*') ? 'true' : null">Plan de cuentas</flux:sidebar.item>
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.accounting.periods.index') }}" icon="calendar-days" :data-current="request()->routeIs('admin.accounting.periods.*') ? 'true' : null">Periodos contables</flux:sidebar.item>
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.accounting.cost-centers.index') }}" icon="building-office" :data-current="request()->routeIs('admin.accounting.cost-centers.*') ? 'true' : null">Centros de costo</flux:sidebar.item>
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.accounting.settings.edit') }}" icon="cog-6-tooth" :data-current="request()->routeIs('admin.accounting.settings.*') ? 'true' : null">Config. contable</flux:sidebar.item>
            </flux:sidebar.group>
        @endif

        @if($modules->has('billing'))
            <flux:sidebar.group heading="Facturacion electronica" icon="document-text" expandable :expanded="request()->routeIs('admin.billing.*') || request()->routeIs('admin.electronic-documents.*')">
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.billing.documents.index') }}" icon="document-duplicate" :data-current="request()->routeIs('admin.billing.documents.*') ? 'true' : null">Docs electronicos</flux:sidebar.item>
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.billing.settings.edit') }}" icon="wrench-screwdriver" :data-current="request()->routeIs('admin.billing.settings.*') ? 'true' : null">Config. facturacion</flux:sidebar.item>
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.billing.operation-types.edit') }}" icon="queue-list" :data-current="request()->routeIs('admin.billing.operation-types.*') ? 'true' : null">Catalogo SUNAT 51</flux:sidebar.item>
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.electronic-documents.templates.index') }}" icon="document-chart-bar" :data-current="request()->routeIs('admin.electronic-documents.templates.*') ? 'true' : null">Plantillas XSLT</flux:sidebar.item>
            </flux:sidebar.group>
        @endif

        @if($modules->has('catalog'))
            <flux:sidebar.group heading="Catalogo">
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.products.index') }}" icon="cube" :data-current="request()->routeIs('admin.products.*') ? 'true' : null">Productos</flux:sidebar.item>
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.categories.index') }}" icon="tag" :data-current="request()->routeIs('admin.categories.*') ? 'true' : null">Categorias</flux:sidebar.item>
                <flux:sidebar.item wire:navigate.hover href="{{ route('admin.unit-measures.index') }}" icon="scale" :data-current="request()->routeIs('admin.unit-measures.*') ? 'true' : null">Unidades</flux:sidebar.item>
            </flux:sidebar.group>
        @endif

        @if($modules->has('commerce') || $modules->has('admin_theme') || $modules->has('security'))
            <flux:sidebar.group heading="Configuracion">
                @if($modules->has('commerce'))
                    <flux:sidebar.item wire:navigate.hover href="{{ route('admin.settings.edit') }}" icon="building-storefront" :data-current="request()->routeIs('admin.settings.*') ? 'true' : null">Comercio</flux:sidebar.item>
                @endif
                @if($modules->has('admin_theme'))
                    <flux:sidebar.item wire:navigate.hover href="{{ route('admin.theme.edit') }}" icon="swatch" :data-current="request()->routeIs('admin.theme.*') ? 'true' : null">Paleta admin</flux:sidebar.item>
                @endif
                @if($modules->has('security'))
                    <flux:sidebar.item wire:navigate.hover href="{{ route('admin.security.authentication.edit') }}" icon="shield-check" :data-current="request()->routeIs('admin.security.authentication.*') ? 'true' : null">Autenticacion</flux:sidebar.item>
                    @if($canViewSecurityRoles)
                        <flux:sidebar.item wire:navigate.hover href="{{ route('admin.security.roles.index') }}" icon="shield-exclamation" :data-current="request()->routeIs('admin.security.roles.*') ? 'true' : null">Roles y permisos</flux:sidebar.item>
                    @endif
                    @if($canViewSecurityUsers)
                        <flux:sidebar.item wire:navigate.hover href="{{ route('admin.security.users.index') }}" icon="user-circle" :data-current="request()->routeIs('admin.security.users.*') ? 'true' : null">Accesos de usuarios</flux:sidebar.item>
                    @endif
                    @if($canViewSecurityBranches)
                        <flux:sidebar.item wire:navigate.hover href="{{ route('admin.security.branches.index') }}" icon="building-storefront" :data-current="request()->routeIs('admin.security.branches.*') ? 'true' : null">Sucursales</flux:sidebar.item>
                    @endif
                    @if($canViewSecurityAudit)
                        <flux:sidebar.item wire:navigate.hover href="{{ route('admin.security.audit.index') }}" icon="clipboard-document-list" :data-current="request()->routeIs('admin.security.audit.*') ? 'true' : null">Auditoria</flux:sidebar.item>
                    @endif
                @endif
                <flux:sidebar.item wire:navigate.hover href="{{ route('orders.mine') }}" icon="shopping-bag">Mis pedidos</flux:sidebar.item>
            </flux:sidebar.group>
        @endif

        @if($modules->has('imports') || $modules->has('finance') || $modules->has('crm') || $modules->has('reports') || $modules->has('inventory') || $modules->has('warranties') || $modules->has('payroll'))
            <flux:sidebar.group heading="Modulos futuros">
                @foreach(['imports' => 'Importaciones', 'finance' => 'Finanzas', 'crm' => 'CRM', 'reports' => 'Reportes', 'inventory' => 'Inventarios', 'warranties' => 'Garantias', 'payroll' => 'Planillas'] as $code => $label)
                    @if($modules->has($code))
                        @if($code === 'inventory')
                            <flux:sidebar.item wire:navigate.hover href="{{ route('admin.inventory.index') }}" icon="archive-box" :data-current="request()->routeIs('admin.inventory.*') ? 'true' : null">
                                {{ $label }}
                            </flux:sidebar.item>
                        @else
                            <flux:sidebar.item wire:navigate.hover href="{{ route('admin.modules.placeholder', $code) }}" icon="sparkles" :data-current="request()->routeIs('admin.modules.placeholder') && request()->route('module')?->code === $code ? 'true' : null">
                                {{ $label }}
                            </flux:sidebar.item>
                        @endif
                    @endif
                @endforeach
            </flux:sidebar.group>
        @endif
    </flux:sidebar.nav>

    <div class="admin-sidebar__footer">
        <flux:button href="{{ route('home') }}" wire:navigate.hover variant="outline" icon="arrow-up-right" class="w-full justify-start">
            Volver a tienda
        </flux:button>
    </div>
</flux:sidebar>



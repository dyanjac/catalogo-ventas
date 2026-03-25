@php
    $statCards = [
        [
            'label' => 'Clientes activos',
            'value' => number_format($stats['customers']),
            'description' => 'Base comercial registrada para campañas y ventas.',
            'icon' => 'users',
            'href' => route('admin.customers.index'),
            'tone' => 'sky',
        ],
        [
            'label' => 'Pedidos totales',
            'value' => number_format($stats['orders']),
            'description' => 'Seguimiento unificado de compras y operaciones.',
            'icon' => 'receipt-percent',
            'href' => route('admin.orders.index'),
            'tone' => 'emerald',
        ],
        [
            'label' => 'Catalogo activo',
            'value' => number_format($stats['products']),
            'description' => 'Productos disponibles para ecommerce y POS.',
            'icon' => 'cube',
            'href' => route('admin.products.index'),
            'tone' => 'amber',
        ],
        [
            'label' => 'Stock critico',
            'value' => number_format($stats['lowStock']),
            'description' => 'Productos que requieren reposicion inmediata.',
            'icon' => 'shield-exclamation',
            'href' => route('admin.products.index', ['is_active' => 1]),
            'tone' => 'rose',
        ],
    ];
@endphp

<div class="dashboard-screen">
    <x-admin.page-header
        title="Dashboard ejecutivo"
        description="Resumen operativo del comercio, ventas recientes y alertas del catálogo."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.orders.index') }}" variant="primary" icon="receipt-percent">
                Revisar pedidos
            </flux:button>
            <flux:button href="{{ route('admin.customers.index') }}" variant="outline" icon="users">
                Ver clientes
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4 mb-4">
        @foreach ($statCards as $card)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ $card['href'] }}" class="dashboard-stat dashboard-stat--{{ $card['tone'] }}">
                    <div class="dashboard-stat__icon">
                        <flux:icon :icon="$card['icon']" class="size-7 text-white" />
                    </div>
                    <div class="dashboard-stat__label">{{ $card['label'] }}</div>
                    <div class="dashboard-stat__value">{{ $card['value'] }}</div>
                    <div class="dashboard-stat__description">{{ $card['description'] }}</div>
                    <div class="dashboard-stat__footer">
                        Abrir modulo
                        <flux:icon.arrow-up-right class="size-4" />
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card border-0 h-100">
                <div class="card-body p-0">
                    <div class="dashboard-panel__header">
                        <div>
                            <h3 class="dashboard-panel__title">Pedidos recientes</h3>
                            <p class="dashboard-panel__meta">Monitorea las últimas operaciones registradas en el panel.</p>
                        </div>
                        <flux:button href="{{ route('admin.orders.index') }}" variant="outline" size="sm" icon="arrow-up-right">
                            Ver todos
                        </flux:button>
                    </div>

                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Estado</th>
                                    <th>Pago</th>
                                    <th>Total</th>
                                    <th class="text-end">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($latestOrders as $order)
                                    <tr>
                                        <td class="fw-semibold">{{ $order->series }}-{{ str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT) }}</td>
                                        <td>
                                            <div class="fw-semibold text-slate-800">{{ $order->user?->name ?? 'Sin usuario' }}</div>
                                            <small class="text-muted">{{ $order->created_at?->format('d/m/Y H:i') }}</small>
                                        </td>
                                        <td><span class="badge badge-light">{{ strtoupper($order->status) }}</span></td>
                                        <td><span class="badge badge-secondary">{{ strtoupper($order->payment_status) }}</span></td>
                                        <td>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
                                        <td class="text-end">
                                            <flux:button href="{{ route('admin.orders.show', $order) }}" variant="primary" size="sm">
                                                Abrir
                                            </flux:button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-5 text-center text-muted">Aun no se registran pedidos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="dashboard-aside">
                <div class="card border-0">
                    <div class="card-body">
                        <h3 class="dashboard-panel__title">Radar de catalogo</h3>
                        <p class="dashboard-panel__meta">Indicadores clave para abastecimiento y estructura del comercio.</p>

                        <div class="dashboard-metric">
                            <span>Categorias</span>
                            <strong>{{ number_format($stats['categories']) }}</strong>
                        </div>
                        <div class="dashboard-metric">
                            <span>Unidades</span>
                            <strong>{{ number_format($stats['unitMeasures']) }}</strong>
                        </div>
                        <div class="dashboard-metric dashboard-metric--alert">
                            <span>Productos con alerta</span>
                            <strong>{{ number_format($stats['lowStock']) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="card border-0">
                    <div class="card-body">
                        <div class="dashboard-panel__header dashboard-panel__header--compact">
                            <div>
                                <h3 class="dashboard-panel__title">Stock bajo</h3>
                                <p class="dashboard-panel__meta">Prioriza reposicion y control comercial.</p>
                            </div>
                            <flux:button href="{{ route('admin.products.index') }}" variant="outline" size="sm" icon="cube">
                                Catalogo
                            </flux:button>
                        </div>

                        <div class="dashboard-stock-list">
                            @forelse ($lowStockProducts as $stock)
                                <a href="{{ route('admin.products.edit', $stock->product) }}" class="dashboard-stock-item">
                                    <div>
                                        <div class="fw-semibold text-slate-900">{{ $stock->product?->name ?? 'Producto sin referencia' }}</div>
                                        <div class="text-sm text-slate-500">
                                            {{ $stock->product?->category?->name ?? 'Sin categoria' }} · {{ $stock->branch?->name ?? 'Sin sucursal' }}
                                        </div>
                                    </div>
                                    <span class="dashboard-stock-badge">{{ $stock->stock }}</span>
                                </a>
                            @empty
                                <div class="dashboard-empty-state">
                                    No hay alertas de stock por ahora.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

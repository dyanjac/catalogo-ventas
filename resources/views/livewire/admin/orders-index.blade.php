<div class="space-y-6">
    <x-admin.page-header
        title="Gestion de pedidos"
        description="Consulta operaciones, filtra por estado y entra al detalle comercial de cada orden."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.dashboard') }}" variant="outline" icon="home">
                Dashboard
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card border-0">
        <div class="card-body">
            <div class="grid gap-4 md:grid-cols-[2fr,1fr,1fr]">
                <div>
                    <label class="form-label">Buscar pedido</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Serie-correlativo, cliente o transaccion" />
                </div>
                <div>
                    <label class="form-label">Estado</label>
                    <select wire:model.live="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Pago</label>
                    <select wire:model.live="paymentStatus" class="form-select">
                        <option value="">Todos</option>
                        @foreach($paymentStatusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button wire:click="clearFilters" variant="outline" icon="arrow-path">
                    Limpiar filtros
                </flux:button>
                <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-500">
                    {{ $orders->total() }} pedidos encontrados
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 overflow-hidden">
        <div class="card-body p-0">
            <div wire:loading.flex class="align-items-center justify-content-center border-bottom px-4 py-3 text-sm text-muted">
                Actualizando pedidos...
            </div>

            <div class="overflow-x-auto">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Total</th>
                            <th>Fecha</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr wire:key="order-{{ $order->id }}">
                                <td class="fw-semibold">{{ $order->series }}-{{ str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT) }}</td>
                                <td>
                                    <div class="fw-semibold text-slate-800">{{ $order->user?->name ?? 'Sin usuario' }}</div>
                                    <small class="text-muted">{{ $order->user?->email }}</small>
                                </td>
                                <td><span class="badge badge-light">{{ strtoupper($order->status) }}</span></td>
                                <td><span class="badge badge-secondary">{{ strtoupper($order->payment_status) }}</span></td>
                                <td>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
                                <td>{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <flux:button href="{{ route('admin.orders.download.pdf', $order) }}" variant="outline" size="sm" icon="document-duplicate">
                                            PDF
                                        </flux:button>
                                        <flux:button href="{{ route('admin.orders.show', $order) }}" variant="primary" size="sm">
                                            Gestionar
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-5 text-center text-muted">No hay pedidos para mostrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        {{ $orders->links() }}
    </div>
</div>

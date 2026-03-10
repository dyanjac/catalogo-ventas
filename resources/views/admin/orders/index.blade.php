@extends('layouts.admin')

@section('title', 'Admin - Pedidos')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Gestion de Pedidos" />

        <x-admin.filter-card>
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Buscar pedido</label>
                        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Serie-correlativo, cliente o transaccion">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="">Todos</option>
                            @foreach(['pending' => 'Pendiente', 'confirmed' => 'Confirmado', 'processing' => 'En proceso', 'delivered' => 'Entregado', 'cancelled' => 'Cancelado'] as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pago</label>
                        <select name="payment_status" class="form-select">
                            <option value="">Todos</option>
                            @foreach(['pending' => 'Pendiente', 'paid' => 'Pagado', 'failed' => 'Fallido', 'refunded' => 'Reembolsado'] as $value => $label)
                                <option value="{{ $value }}" @selected($paymentStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary rounded-pill px-4">Filtrar</button>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-light border rounded-pill px-4">Limpiar</a>
                    </div>
                </div>
            </form>
        </x-admin.filter-card>

        <x-admin.data-table :colspan="7" empty-message="No hay pedidos para mostrar.">
            <x-slot:head>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Pago</th>
                        <th>Total</th>
                        <th>Fecha</th>
                        <th class="text-end">Acciones</th>
                    </tr>
            </x-slot:head>
            @forelse($orders as $order)
                        <tr>
                            <td>{{ $order->series }}-{{ str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                <div>{{ $order->user?->name ?? 'Sin usuario' }}</div>
                                <small class="text-muted">{{ $order->user?->email }}</small>
                            </td>
                            <td><span class="badge bg-light text-dark">{{ strtoupper($order->status) }}</span></td>
                            <td><span class="badge bg-secondary">{{ strtoupper($order->payment_status) }}</span></td>
                            <td>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
                            <td>{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.orders.download.pdf', $order) }}" class="btn btn-sm btn-light border" title="Descargar PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-primary">Gestionar</a>
                            </td>
                        </tr>
            @empty
            @endforelse
        </x-admin.data-table>

        <x-admin.pagination :paginator="$orders" />
    </div>
</div>
@endsection


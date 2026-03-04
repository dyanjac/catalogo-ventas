@extends('layouts.admin')

@section('title', 'Admin - Pedidos')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary mb-0">Gestion de Pedidos</h1>
        </div>

        <form method="GET" class="card border border-secondary rounded-3 mb-4">
            <div class="card-body">
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
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-light">
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
                                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-primary">Gestionar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No hay pedidos para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            {{ $orders->links('pagination::bootstrap-4') }}
        </div>
    </div>
</div>
@endsection


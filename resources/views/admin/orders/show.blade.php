@extends('layouts.admin')

@section('title', 'Gestionar pedido')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-primary mb-0">Pedido {{ $order->series }}-{{ str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT) }}</h1>
                <p class="text-muted mb-0">Cliente: {{ $order->user?->name ?? 'Sin usuario' }} · {{ $order->created_at?->format('d/m/Y H:i') }}</p>
            </div>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border border-secondary rounded-3">
                    <div class="card-body">
                        <h4 class="text-primary mb-3">Detalle del pedido</h4>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio</th>
                                        <th>Imp.</th>
                                        <th>Desc.</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($order->items as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item->product?->name ?? 'Producto eliminado' }}</div>
                                                <small class="text-muted">{{ $item->product?->sku ?? 'Sin SKU' }}</small>
                                            </td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>{{ $item->currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                                            <td>{{ $item->currency }} {{ number_format((float) $item->tax_amount, 2) }}</td>
                                            <td>{{ $item->currency }} {{ number_format((float) $item->discount_amount, 2) }}</td>
                                            <td class="text-end">{{ $item->currency }} {{ number_format((float) $item->line_total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">El pedido no tiene items.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card border border-secondary rounded-3 mt-4">
                    <div class="card-body">
                        <h4 class="text-primary mb-3">Entrega</h4>
                        @php($shipping = $order->shipping_address ?? [])
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">Nombre</div>
                                <div>{{ $shipping['name'] ?? '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Celular</div>
                                <div>{{ $shipping['phone'] ?? '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Ciudad</div>
                                <div>{{ $shipping['city'] ?? '-' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Direccion</div>
                                <div>{{ $shipping['address'] ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="card border border-secondary rounded-3">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <h4 class="text-primary mb-3">Control comercial</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-select">
                                    @foreach(['pending' => 'Pendiente', 'confirmed' => 'Confirmado', 'processing' => 'En proceso', 'delivered' => 'Entregado', 'cancelled' => 'Cancelado'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', $order->status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estado de pago</label>
                                <select name="payment_status" class="form-select">
                                    @foreach(['pending' => 'Pendiente', 'paid' => 'Pagado', 'failed' => 'Fallido', 'refunded' => 'Reembolsado'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_status', $order->payment_status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Metodo de pago</label>
                                <select name="payment_method" class="form-select">
                                    @foreach(['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card' => 'Tarjeta', 'yape' => 'Yape'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_method', $order->payment_method) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Transaccion</label>
                                <input type="text" name="transaction_id" class="form-control" value="{{ old('transaction_id', $order->transaction_id) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observaciones</label>
                                <textarea name="observations" rows="4" class="form-control">{{ old('observations', $order->observations) }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Actualizar pedido</button>
                    </div>
                </form>

                <div class="card border border-secondary rounded-3 mt-4">
                    <div class="card-body">
                        <h4 class="text-primary mb-3">Totales</h4>
                        <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong>{{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>Descuento</span><strong>{{ $order->currency }} {{ number_format((float) $order->discount, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>Impuesto</span><strong>{{ $order->currency }} {{ number_format((float) $order->tax, 2) }}</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>Envio</span><strong>{{ $order->currency }} {{ number_format((float) $order->shipping, 2) }}</strong></div>
                        <hr>
                        <div class="d-flex justify-content-between"><span class="fw-semibold">Total</span><strong>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</strong></div>
                        <div class="text-muted small mt-3">Pagado el: {{ $order->paid_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


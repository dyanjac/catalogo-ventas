@extends('layouts.app')

@section('title', 'Mis Pedidos')

@section('content')
<div class="container py-5">
    <h1 class="mb-4 text-primary">Mis Pedidos</h1>

    @include('partials.flash')

    @forelse($orders as $order)
        <div class="card border border-secondary mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <strong>Pedido {{ $order->series }}-{{ str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT) }}</strong>
                    <span class="text-muted ms-2">{{ $order->created_at?->format('d/m/Y H:i') }}</span>
                </div>
                <span class="badge bg-primary">{{ strtoupper($order->status) }}</span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Subtotal:</strong> {{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}</div>
                    <div class="col-md-3"><strong>Descuento:</strong> {{ $order->currency }} {{ number_format((float) $order->discount, 2) }}</div>
                    <div class="col-md-3"><strong>IGV/Impuesto:</strong> {{ $order->currency }} {{ number_format((float) $order->tax, 2) }}</div>
                    <div class="col-md-3"><strong>Envío:</strong> {{ $order->currency }} {{ number_format((float) $order->shipping, 2) }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Total:</strong> {{ $order->currency }} {{ number_format((float) $order->total, 2) }}</div>
                    <div class="col-md-3"><strong>Método pago:</strong> {{ strtoupper((string) $order->payment_method) }}</div>
                    <div class="col-md-3"><strong>Estado pago:</strong> {{ strtoupper((string) $order->payment_status) }}</div>
                    <div class="col-md-3"><strong>Pago fecha:</strong> {{ $order->paid_at?->format('d/m/Y H:i') ?? '-' }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6"><strong>ID Transacción:</strong> {{ $order->transaction_id ?? '-' }}</div>
                    <div class="col-md-6"><strong>Observaciones:</strong> {{ $order->observations ?? '-' }}</div>
                </div>

                @if(is_array($order->shipping_address))
                    <div class="mb-3">
                        <strong>Entrega:</strong>
                        {{ $order->shipping_address['name'] ?? '-' }},
                        {{ $order->shipping_address['address'] ?? '-' }},
                        {{ $order->shipping_address['city'] ?? '-' }},
                        Tel: {{ $order->shipping_address['phone'] ?? '-' }}
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Moneda</th>
                                <th class="text-end">Precio Unit.</th>
                                <th class="text-end">Desc.</th>
                                <th class="text-end">Imp.</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>{{ $item->product?->name ?? ('Producto #' . $item->product_id) }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end">{{ $item->currency }}</td>
                                    <td class="text-end">{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->discount_amount, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->tax_amount, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="alert alert-light border">Aún no tienes pedidos registrados.</div>
    @endforelse

    <div class="d-flex justify-content-center mt-4">
        {{ $orders->links() }}
    </div>
</div>
@endsection

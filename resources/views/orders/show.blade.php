@extends('layouts.app')

@section('title', 'Detalle del Pedido')

@section('content')
@php
    $orderCode = $order->series . '-' . str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT);
    $statusClass = match ($order->status) {
        'confirmed' => 'bg-success',
        'pending' => 'bg-warning text-dark',
        'canceled' => 'bg-danger',
        default => 'bg-primary',
    };
    $paymentClass = match ($order->payment_status) {
        'paid' => 'bg-success',
        'failed' => 'bg-danger',
        'refunded' => 'bg-secondary',
        default => 'bg-warning text-dark',
    };
@endphp

<section class="container-fluid py-5 mt-5 mp-shell">
    <div class="container py-4">
        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Detalle de pedido</span>
                <h1>{{ $orderCode }}</h1>
                <p>Consulta cabecera, entrega, pago e ítems del pedido en una vista separada del listado principal.</p>
            </div>
            <a href="{{ route('orders.mine', request()->query()) }}" class="btn btn-light border rounded-pill px-4">Volver al listado</a>
        </div>

        <div class="mp-cart-panel mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <div class="d-flex gap-2 flex-wrap mb-2">
                        <span class="badge {{ $statusClass }}">{{ strtoupper((string) $order->status) }}</span>
                        <span class="badge {{ $paymentClass }}">{{ strtoupper((string) $order->payment_status) }}</span>
                    </div>
                    <div class="text-muted">Emitido el {{ $order->created_at?->format('d/m/Y H:i') }}</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">Repetir compra</a>
                    @if(!empty($commerce['mobile_digits']))
                        <a href="{{ $commerce['whatsapp_url'] }}?text={{ urlencode('Hola, quiero hacer seguimiento a mi pedido ' . $orderCode . '.') }}" target="_blank" rel="noreferrer" class="btn btn-light border rounded-pill px-4">
                            Seguimiento por WhatsApp
                        </a>
                    @elseif(!empty($commerce['phone_digits']))
                        <a href="tel:{{ $commerce['phone_digits'] }}" class="btn btn-light border rounded-pill px-4">
                            Llamar al comercio
                        </a>
                    @endif
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="border rounded-4 p-3 h-100 bg-white">
                                <div class="text-muted small mb-1">Cliente y entrega</div>
                                <div class="fw-semibold">{{ data_get($order->shipping_address, 'name', '-') }}</div>
                                <div>{{ data_get($order->shipping_address, 'address', '-') }}</div>
                                <div>{{ data_get($order->shipping_address, 'city', '-') }}</div>
                                <div>Tel: {{ data_get($order->shipping_address, 'phone', '-') }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-4 p-3 h-100 bg-white">
                                <div class="text-muted small mb-1">Pago</div>
                                <div><strong>Método:</strong> {{ strtoupper((string) $order->payment_method) }}</div>
                                <div><strong>Estado:</strong> {{ strtoupper((string) $order->payment_status) }}</div>
                                <div><strong>Fecha:</strong> {{ $order->paid_at?->format('d/m/Y H:i') ?? 'Pendiente' }}</div>
                                <div><strong>Transacción:</strong> {{ $order->transaction_id ?? 'Sin referencia' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mp-cart-panel">
                        <div class="d-grid gap-3">
                            @foreach($order->items as $item)
                                <div class="border rounded-4 p-3 bg-white">
                                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                        <div>
                                            <div class="fw-semibold">{{ $item->product?->name ?? ('Producto #' . $item->product_id) }}</div>
                                            <div class="text-muted small">{{ $item->quantity }} unidad(es) x {{ $item->currency }} {{ number_format((float) $item->unit_price, 2) }}</div>
                                        </div>
                                        <div class="text-lg-end">
                                            <div class="fw-semibold">{{ $item->currency }} {{ number_format((float) $item->line_total, 2) }}</div>
                                            <div class="text-muted small">Desc. {{ number_format((float) $item->discount_amount, 2) }} | Imp. {{ number_format((float) $item->tax_amount, 2) }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="mp-cart-summary">
                        <span class="mp-kicker">Resumen financiero</span>
                        <h5 class="mb-3">Totales del pedido</h5>
                        <div class="mp-summary-row"><span>Subtotal</span><strong>{{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}</strong></div>
                        <div class="mp-summary-row"><span>Descuento</span><strong>{{ $order->currency }} {{ number_format((float) $order->discount, 2) }}</strong></div>
                        <div class="mp-summary-row"><span>Impuesto</span><strong>{{ $order->currency }} {{ number_format((float) $order->tax, 2) }}</strong></div>
                        <div class="mp-summary-row"><span>Envío</span><strong>{{ $order->currency }} {{ number_format((float) $order->shipping, 2) }}</strong></div>
                        <div class="mp-summary-row mp-summary-total"><span>Total</span><strong>{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</strong></div>

                        <div class="alert alert-light border rounded-4 mt-4 mb-0">
                            {{ $order->observations ?: 'Sin observaciones registradas para este pedido.' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

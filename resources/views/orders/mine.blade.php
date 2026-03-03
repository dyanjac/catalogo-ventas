@extends('layouts.app')

@section('title', 'Mis Pedidos')

@section('content')
@php
    $latestOrderNumber = session('latest_order_number');
@endphp

<section class="container-fluid py-5 mt-5 mp-shell">
    <div class="container py-4">
        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Pedidos</span>
                <h1>Listado de pedidos</h1>
                <p>Ubica rápidamente tus órdenes por cliente, número o fecha de emisión y abre el detalle solo cuando lo necesites.</p>
            </div>
            <a href="{{ route('catalog.index') }}" class="btn btn-light border rounded-pill px-4">Seguir comprando</a>
        </div>

        @include('partials.flash')

        @if($latestOrderNumber)
            <div class="mp-cart-summary mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <span class="mp-kicker">Pedido confirmado</span>
                        <h4 class="mb-2">Registro completado: {{ $latestOrderNumber }}</h4>
                        <p class="mb-0 text-muted">Tu pedido ya quedó grabado. Puedes ubicarlo también con el buscador por número de pedido.</p>
                    </div>
                    <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">Volver al catálogo</a>
                </div>
            </div>
        @endif

        <div class="mp-cart-panel mb-4">
            <form method="GET" action="{{ route('orders.mine') }}" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label for="order-search" class="form-label">Número de pedido o transacción</label>
                    <input
                        type="text"
                        name="search"
                        id="order-search"
                        class="form-control"
                        value="{{ $search }}"
                        placeholder="Ej. PED-00000012 o TRX-001"
                    >
                </div>
                <div class="col-lg-3">
                    <label for="order-customer" class="form-label">Cliente</label>
                    <input
                        type="text"
                        name="customer"
                        id="order-customer"
                        class="form-control"
                        value="{{ $customer }}"
                        placeholder="Nombre de entrega"
                    >
                </div>
                <div class="col-lg-2">
                    <label for="order-date-from" class="form-label">Desde</label>
                    <input type="date" name="date_from" id="order-date-from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-lg-2">
                    <label for="order-date-to" class="form-label">Hasta</label>
                    <input type="date" name="date_to" id="order-date-to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-lg-1 d-grid">
                    <button class="btn btn-primary rounded-pill">Filtrar</button>
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <a href="{{ route('orders.mine') }}" class="btn btn-light border rounded-pill px-4">Limpiar</a>
                    <div class="text-muted small align-self-center">
                        {{ $orders->total() }} resultado(s) encontrados.
                    </div>
                </div>
            </form>
        </div>

        @forelse($orders as $order)
            @php
                $orderCode = $order->series . '-' . str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT);
                $isLatest = $latestOrderNumber === $orderCode;
                $customerName = data_get($order->shipping_address, 'name', auth()->user()->name);
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

            <div class="mp-cart-panel mb-3 {{ $isLatest ? 'border border-success shadow-sm' : '' }}">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-3">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <span class="mp-kicker">{{ $isLatest ? 'Nuevo' : 'Pedido' }}</span>
                            <span class="badge {{ $statusClass }}">{{ strtoupper((string) $order->status) }}</span>
                            <span class="badge {{ $paymentClass }}">{{ strtoupper((string) $order->payment_status) }}</span>
                        </div>
                        <h5 class="mb-1">{{ $orderCode }}</h5>
                        <div class="text-muted small">{{ $order->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="col-lg-3">
                        <div class="text-muted small">Cliente</div>
                        <div class="fw-semibold">{{ $customerName }}</div>
                        <div class="text-muted small">{{ data_get($order->shipping_address, 'city', '-') }}</div>
                    </div>
                    <div class="col-lg-2">
                        <div class="text-muted small">Ítems</div>
                        <div class="fw-semibold">{{ $order->items_count }}</div>
                    </div>
                    <div class="col-lg-2">
                        <div class="text-muted small">Total</div>
                        <div class="fw-semibold">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</div>
                    </div>
                    <div class="col-lg-2 text-lg-end">
                        <a href="{{ route('orders.show', array_merge(['order' => $order], request()->query())) }}" class="btn btn-primary rounded-pill px-4">Ver detalle</a>
                    </div>
                </div>
            </div>
        @empty
            <div class="mp-empty-state">
                <h3>No se encontraron pedidos</h3>
                <p>Ajusta los filtros o vuelve al catálogo para registrar una nueva orden.</p>
                <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">Ir al catálogo</a>
            </div>
        @endforelse

        <div class="d-flex justify-content-center mt-4">
            {{ $orders->links() }}
        </div>
    </div>
</section>
@endsection

@extends('layouts.admin')

@section('title', 'Gestionar pedido')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header
            :title="'Pedido ' . $order->series . '-' . str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT)"
            :description="'Cliente: ' . ($order->user?->name ?? 'Sin usuario') . ' · ' . ($order->created_at?->format('d/m/Y H:i') ?? '')"
        >
            <x-slot:actions>
                <x-admin.action-bar>
                    <a href="{{ route('admin.orders.download.pdf', $order) }}" class="btn btn-light border rounded-pill px-4">
                        <i class="fas fa-file-pdf mr-1"></i> PDF
                    </a>
                    <a href="{{ route('admin.orders.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
                </x-admin.action-bar>
            </x-slot:actions>
        </x-admin.page-header>

        <div class="row g-4">
            <div class="col-lg-7">
                <x-admin.info-card title="Detalle del pedido">
                    <div class="d-grid gap-3">
                        @forelse($order->items as $item)
                            <div class="border rounded-3 p-3">
                                <x-admin.detail-grid
                                    :items="[
                                        ['label' => 'Producto', 'value' => ($item->product?->name ?? 'Producto eliminado') . ' · ' . ($item->product?->sku ?? 'Sin SKU'), 'class' => 'col-12'],
                                        ['label' => 'Cantidad', 'value' => $item->quantity, 'class' => 'col-md-2'],
                                        ['label' => 'Precio', 'value' => $item->currency . ' ' . number_format((float) $item->unit_price, 2), 'class' => 'col-md-2'],
                                        ['label' => 'Imp.', 'value' => $item->currency . ' ' . number_format((float) $item->tax_amount, 2), 'class' => 'col-md-2'],
                                        ['label' => 'Desc.', 'value' => $item->currency . ' ' . number_format((float) $item->discount_amount, 2), 'class' => 'col-md-2'],
                                        ['label' => 'Total', 'value' => $item->currency . ' ' . number_format((float) $item->line_total, 2), 'class' => 'col-md-4'],
                                    ]"
                                />
                            </div>
                        @empty
                            <div class="text-center text-muted">El pedido no tiene items.</div>
                        @endforelse
                    </div>
                </x-admin.info-card>

                <x-admin.info-card title="Entrega" class="mt-4">
                        @php($shipping = $order->shipping_address ?? [])
                        <x-admin.detail-grid
                            :items="[
                                ['label' => 'Nombre', 'value' => $shipping['name'] ?? '-'],
                                ['label' => 'Celular', 'value' => $shipping['phone'] ?? '-'],
                                ['label' => 'Ciudad', 'value' => $shipping['city'] ?? '-'],
                                ['label' => 'Direccion', 'value' => $shipping['address'] ?? '-'],
                            ]"
                            columns="col-md-6"
                        />
                </x-admin.info-card>
            </div>

            <div class="col-lg-5">
                <x-admin.form-card
                    :action="route('admin.orders.update', $order)"
                    method="PUT"
                    submit-label="Actualizar pedido"
                    :cancel-href="route('admin.orders.index')"
                    title="Control comercial"
                >
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
                </x-admin.form-card>

                <x-admin.info-card title="Totales" class="mt-4">
                        <x-admin.detail-grid
                            :items="[
                                ['label' => 'Subtotal', 'value' => $order->currency . ' ' . number_format((float) $order->subtotal, 2), 'class' => 'col-6'],
                                ['label' => 'Descuento', 'value' => $order->currency . ' ' . number_format((float) $order->discount, 2), 'class' => 'col-6'],
                                ['label' => 'Impuesto', 'value' => $order->currency . ' ' . number_format((float) $order->tax, 2), 'class' => 'col-6'],
                                ['label' => 'Envio', 'value' => $order->currency . ' ' . number_format((float) $order->shipping, 2), 'class' => 'col-6'],
                                ['label' => 'Total', 'value' => $order->currency . ' ' . number_format((float) $order->total, 2), 'class' => 'col-6'],
                                ['label' => 'Pagado el', 'value' => $order->paid_at?->format('d/m/Y H:i') ?? 'Pendiente', 'class' => 'col-6'],
                            ]"
                            columns="col-6"
                        />
                </x-admin.info-card>
            </div>
        </div>
    </div>
</div>
@endsection


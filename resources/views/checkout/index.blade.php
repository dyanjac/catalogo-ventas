@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
@php
    $user = auth()->user();
@endphp
<section class="container-fluid py-5 mt-5 mp-shell">
    <div class="container py-4">
        <div class="mp-section-head mb-4">
            <div>
                <span class="mp-kicker">Checkout comercial</span>
                <h1>Confirma tu despacho y graba el pedido</h1>
                <p>Validamos stock, mantenemos tus datos y te mostramos el total final antes de cerrar la compra.</p>
            </div>
            <a href="{{ route('cart.view') }}" class="btn btn-light border rounded-pill px-4">Volver al carrito</a>
        </div>

        @include('partials.flash')

        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <div class="mp-cart-panel">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <div>
                            <span class="mp-kicker">Despacho</span>
                            <h4 class="mb-1">Datos del cliente y entrega</h4>
                            <p class="text-muted mb-0">Usamos tu información para registrar el pedido y coordinar contacto comercial.</p>
                        </div>
                        @if($user?->email)
                            <div class="badge bg-light text-dark border px-3 py-2 rounded-pill">{{ $user->email }}</div>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('checkout.store') }}" class="d-grid gap-4">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="checkout-name" class="form-label">Nombre completo</label>
                                <input
                                    type="text"
                                    name="name"
                                    id="checkout-name"
                                    class="form-control"
                                    value="{{ old('name', $user?->name) }}"
                                    placeholder="Nombre o razón social"
                                    required
                                >
                            </div>
                            <div class="col-md-6">
                                <label for="checkout-phone" class="form-label">Celular</label>
                                <input
                                    type="text"
                                    name="phone"
                                    id="checkout-phone"
                                    class="form-control"
                                    value="{{ old('phone', $user?->phone) }}"
                                    placeholder="Número de contacto"
                                    required
                                >
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="checkout-address" class="form-label">Dirección de entrega</label>
                                <input
                                    type="text"
                                    name="address"
                                    id="checkout-address"
                                    class="form-control"
                                    value="{{ old('address', $user?->address) }}"
                                    placeholder="Calle, referencia o zona"
                                    required
                                >
                            </div>
                            <div class="col-md-4">
                                <label for="checkout-city" class="form-label">Ciudad</label>
                                <input
                                    type="text"
                                    name="city"
                                    id="checkout-city"
                                    class="form-control"
                                    value="{{ old('city', $user?->city) }}"
                                    placeholder="Ciudad"
                                    required
                                >
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="checkout-series" class="form-label">Serie</label>
                                <input type="text" name="series" id="checkout-series" class="form-control" value="{{ old('series', 'PED') }}" maxlength="4" placeholder="PED">
                            </div>
                            <div class="col-md-3">
                                <label for="checkout-currency" class="form-label">Moneda</label>
                                <select name="currency" id="checkout-currency" class="form-select">
                                    <option value="PEN" @selected(old('currency', 'PEN') === 'PEN')>PEN</option>
                                    <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="checkout-discount" class="form-label">Descuento</label>
                                <input type="number" step="0.01" min="0" name="discount" id="checkout-discount" value="{{ old('discount', '0') }}" class="form-control" placeholder="0.00">
                            </div>
                            <div class="col-md-3">
                                <label for="checkout-shipping" class="form-label">Envío</label>
                                <input type="number" step="0.01" min="0" name="shipping" id="checkout-shipping" value="{{ old('shipping', '0') }}" class="form-control" placeholder="0.00">
                            </div>
                        </div>

                        <input type="hidden" name="tax_rate" id="checkout-tax-rate" value="{{ old('tax_rate', '0.18') }}">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="checkout-payment-method" class="form-label">Método de pago</label>
                                <select name="payment_method" id="checkout-payment-method" class="form-select">
                                    <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Efectivo</option>
                                    <option value="transfer" @selected(old('payment_method') === 'transfer')>Transferencia</option>
                                    <option value="card" @selected(old('payment_method') === 'card')>Tarjeta</option>
                                    <option value="yape" @selected(old('payment_method') === 'yape')>Yape/Plin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="checkout-payment-status" class="form-label">Estado de pago</label>
                                <select name="payment_status" id="checkout-payment-status" class="form-select">
                                    <option value="pending" @selected(old('payment_status', 'pending') === 'pending')>Pendiente</option>
                                    <option value="paid" @selected(old('payment_status') === 'paid')>Pagado</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="checkout-transaction" class="form-label">ID de transacción</label>
                                <input type="text" name="transaction_id" id="checkout-transaction" class="form-control" value="{{ old('transaction_id') }}" placeholder="Opcional para transferencias, tarjeta o Yape/Plin">
                            </div>
                            <div class="col-12">
                                <label for="checkout-observations" class="form-label">Observaciones</label>
                                <textarea name="observations" id="checkout-observations" class="form-control" rows="3" placeholder="Indicaciones de entrega, horario o notas comerciales">{{ old('observations') }}</textarea>
                            </div>
                        </div>

                        <div class="mp-info-strip">
                            <div class="mp-info-chip"><i class="fa fa-box"></i><span>Stock validado al confirmar</span></div>
                            <div class="mp-info-chip"><i class="fa fa-shield-alt"></i><span>Registro seguro del pedido</span></div>
                        </div>

                        <button class="btn btn-primary btn-lg rounded-pill">Confirmar y grabar pedido</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="mp-cart-summary">
                    <span class="mp-kicker">Resumen</span>
                    <h4 class="mb-3">Pedido listo para cierre</h4>

                    <div class="d-grid gap-3 mb-4">
                        @foreach ($cart as $item)
                            <div class="border rounded-4 p-3 bg-white">
                                <div class="d-flex justify-content-between gap-3 align-items-start">
                                    <div>
                                        <div class="fw-semibold">{{ $item['name'] }}</div>
                                        <small class="text-muted">{{ $item['quantity'] }} x S/ {{ number_format((float) $item['price'], 2) }}</small>
                                    </div>
                                    <strong>S/ {{ number_format((float) $item['price'] * (int) $item['quantity'], 2) }}</strong>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mp-summary-row"><span>Subtotal</span><strong id="summary-subtotal">S/ {{ number_format((float) $subtotal, 2) }}</strong></div>
                    <div class="mp-summary-row"><span>Descuento</span><strong id="summary-discount">S/ 0.00</strong></div>
                    <div class="mp-summary-row"><span>IGV (18%)</span><strong id="summary-tax">S/ {{ number_format((float) $subtotal * 0.18, 2) }}</strong></div>
                    <div class="mp-summary-row"><span>Envío</span><strong id="summary-shipping">S/ 0.00</strong></div>
                    <div class="mp-summary-row mp-summary-total"><span>Total</span><strong id="summary-total">S/ {{ number_format((float) $subtotal * 1.18, 2) }}</strong></div>

                    <div class="alert alert-light border rounded-4 mt-4 mb-0">
                        El precio final se recalcula con el stock y valor actual del producto antes de grabar el pedido.
                    </div>
                </div>
            </div>
        </div>
    </div>    
</section>
<script>
    (function () {
        const subtotal = {{ (float) $subtotal }};
        const discountInput = document.getElementById('checkout-discount');
        const shippingInput = document.getElementById('checkout-shipping');
        const taxRateInput = document.getElementById('checkout-tax-rate');

        const summaryDiscount = document.getElementById('summary-discount');
        const summaryTax = document.getElementById('summary-tax');
        const summaryShipping = document.getElementById('summary-shipping');
        const summaryTotal = document.getElementById('summary-total');
        const summarySubtotal = document.getElementById('summary-subtotal');

        const fmt = (value) => `S/ ${value.toFixed(2)}`;

        const recalc = () => {
            const discount = Math.max(0, Math.min(parseFloat(discountInput.value || '0'), subtotal));
            const shipping = Math.max(0, parseFloat(shippingInput.value || '0'));
            const taxRate = Math.max(0, Math.min(parseFloat(taxRateInput.value || '0.18'), 1));

            const taxableBase = Math.max(0, subtotal - discount);
            const tax = taxableBase * taxRate;
            const total = taxableBase + tax + shipping;

            summarySubtotal.textContent = fmt(subtotal);
            summaryDiscount.textContent = fmt(discount);
            summaryTax.textContent = fmt(tax);
            summaryShipping.textContent = fmt(shipping);
            summaryTotal.textContent = fmt(total);
        };

        discountInput.addEventListener('input', recalc);
        shippingInput.addEventListener('input', recalc);
        recalc();
    })();
</script>
@endsection

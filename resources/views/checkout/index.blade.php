@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<div class="container py-5">
    <h1 class="mb-4 text-primary">Checkout</h1>

    @include('partials.flash')

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cart as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td class="text-center">{{ $item['quantity'] }}</td>
                                <td class="text-end">S/ {{ number_format((float) $item['price'], 2) }}</td>
                                <td class="text-end fw-semibold">S/ {{ number_format((float) $item['price'] * (int) $item['quantity'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <a href="{{ route('cart.view') }}" class="btn btn-light border rounded-pill px-4">Volver al carrito</a>
        </div>

        <div class="col-lg-4">
            <div class="card border border-secondary">
                <div class="card-body">
                    <h5 class="card-title mb-3">Confirmar y grabar pedido</h5>
                    <p class="mb-1 d-flex justify-content-between"><span>Subtotal</span><strong id="summary-subtotal">S/ {{ number_format((float) $subtotal, 2) }}</strong></p>
                    <p class="mb-1 d-flex justify-content-between"><span>Descuento</span><strong id="summary-discount">S/ 0.00</strong></p>
                    <p class="mb-1 d-flex justify-content-between"><span>IGV (18%)</span><strong id="summary-tax">S/ {{ number_format((float) $subtotal * 0.18, 2) }}</strong></p>
                    <p class="mb-3 d-flex justify-content-between"><span>Envío</span><strong id="summary-shipping">S/ 0.00</strong></p>
                    <hr>
                    <p class="mb-4 d-flex justify-content-between fs-5"><span>Total</span><strong id="summary-total">S/ {{ number_format((float) $subtotal * 1.18, 2) }}</strong></p>

                    <form method="POST" action="{{ route('checkout.store') }}" class="d-grid gap-2">
                        @csrf
                        <input type="text" name="name" class="form-control" placeholder="Nombre completo" required>
                        <input type="text" name="address" class="form-control" placeholder="Dirección" required>
                        <input type="text" name="city" class="form-control" placeholder="Ciudad" required>
                        <input type="text" name="phone" class="form-control" placeholder="Celular" required>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="text" name="series" class="form-control" value="PED" maxlength="4" placeholder="Serie">
                            </div>
                            <div class="col-6">
                                <select name="currency" class="form-select">
                                    <option value="PEN" selected>PEN</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" step="0.01" min="0" name="discount" id="checkout-discount" value="0" class="form-control" placeholder="Descuento">
                            </div>
                            <div class="col-6">
                                <input type="number" step="0.01" min="0" name="shipping" id="checkout-shipping" value="0" class="form-control" placeholder="Envío">
                            </div>
                        </div>
                        <input type="hidden" name="tax_rate" id="checkout-tax-rate" value="0.18">
                        <select name="payment_method" class="form-select">
                            <option value="cash" selected>Efectivo</option>
                            <option value="transfer">Transferencia</option>
                            <option value="card">Tarjeta</option>
                            <option value="yape">Yape/Plin</option>
                        </select>
                        <select name="payment_status" class="form-select">
                            <option value="pending" selected>Pendiente</option>
                            <option value="paid">Pagado</option>
                        </select>
                        <input type="text" name="transaction_id" class="form-control" placeholder="ID transacción (opcional)">
                        <textarea name="observations" class="form-control" rows="2" placeholder="Observaciones del pedido"></textarea>
                        <button class="btn btn-primary">Confirmar y grabar pedido</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
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

        const fmt = (value) => `S/ ${value.toFixed(2)}`;

        const recalc = () => {
            const discount = Math.max(0, Math.min(parseFloat(discountInput.value || '0'), subtotal));
            const shipping = Math.max(0, parseFloat(shippingInput.value || '0'));
            const taxRate = Math.max(0, Math.min(parseFloat(taxRateInput.value || '0.18'), 1));

            const taxableBase = Math.max(0, subtotal - discount);
            const tax = taxableBase * taxRate;
            const total = taxableBase + tax + shipping;

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

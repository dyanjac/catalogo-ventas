@extends('layouts.admin')

@section('title', 'Punto de venta')

@php
    $selectedDocumentType = old('document_type', 'order');
    $productIndex = $products->map(function ($product) {
        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'sku' => (string) ($product->sku ?: 'SIN-SKU'),
            'stock' => (int) $product->stock,
            'price' => round((float) ($product->sale_price ?? $product->price ?? 0), 2),
            'label' => (string) ($product->name . ' (' . ($product->sku ?: 'SIN-SKU') . ')'),
        ];
    })->values();
@endphp

@section('content')
<div class="sales-pos-page py-2">
    <x-admin.page-header title="Punto de venta (POS)">
        <x-slot:actions>
            <a href="{{ route('admin.billing.documents.index') }}" class="btn btn-light border rounded-pill px-4">Ver docs electrónicos</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.sales.pos.store') }}" class="sales-pos-form">
        @csrf

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card border-0 sales-pos-card mb-4">
                    <div class="card-body p-3 p-md-4">
                        <div class="sales-pos-hero">
                            <div>
                                <div class="sales-pos-eyebrow">Emisión rápida</div>
                                <h3 class="sales-pos-title mb-1">Crea un pedido, boleta o factura en una sola pantalla</h3>
                                <p class="text-muted mb-0">Define el tipo de comprobante, agrega cliente e ítems, y valida el total antes de emitir.</p>
                            </div>
                            <div class="sales-pos-doc-switch">
                                <button type="button" class="sales-doc-chip {{ $selectedDocumentType === 'order' ? 'is-active' : '' }}" data-doc-type="order">
                                    <span class="sales-doc-chip__title">Pedido POS</span>
                                    <span class="sales-doc-chip__meta">Sin emisión electrónica</span>
                                </button>
                                <button type="button" class="sales-doc-chip {{ $selectedDocumentType === 'boleta' ? 'is-active' : '' }}" data-doc-type="boleta">
                                    <span class="sales-doc-chip__title">Boleta</span>
                                    <span class="sales-doc-chip__meta">Cliente con documento</span>
                                </button>
                                <button type="button" class="sales-doc-chip {{ $selectedDocumentType === 'factura' ? 'is-active' : '' }}" data-doc-type="factura">
                                    <span class="sales-doc-chip__title">Factura</span>
                                    <span class="sales-doc-chip__meta">Cliente con RUC</span>
                                </button>
                            </div>
                            <select name="document_type" id="document_type" class="d-none" required>
                                <option value="order" @selected($selectedDocumentType === 'order')>Pedido POS</option>
                                <option value="boleta" @selected($selectedDocumentType === 'boleta')>Boleta electrónica</option>
                                <option value="factura" @selected($selectedDocumentType === 'factura')>Factura electrónica</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card border-0 sales-pos-card mb-4">
                    <div class="card-header sales-pos-section-header bg-transparent border-0 pb-0">
                        <div>
                            <h4 class="mb-1">Condiciones de venta</h4>
                            <p class="text-muted mb-0">Configura moneda, pago e impuesto antes de registrar los ítems.</p>
                        </div>
                    </div>
                    <div class="card-body p-3 p-md-4 pt-3">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Moneda</label>
                                <select name="currency" class="form-select" required>
                                    <option value="PEN" @selected(old('currency', $defaultCurrency) === 'PEN')>PEN</option>
                                    <option value="USD" @selected(old('currency', $defaultCurrency) === 'USD')>USD</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Método pago</label>
                                <select name="payment_method" id="payment_method" class="form-select" required>
                                    <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Efectivo</option>
                                    <option value="transfer" @selected(old('payment_method') === 'transfer')>Transferencia</option>
                                    <option value="card" @selected(old('payment_method') === 'card')>Tarjeta</option>
                                    <option value="yape" @selected(old('payment_method') === 'yape')>Yape</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado pago</label>
                                <select name="payment_status" id="payment_status" class="form-select" required>
                                    <option value="pending" @selected(old('payment_status', 'pending') === 'pending')>Pendiente</option>
                                    <option value="paid" @selected(old('payment_status') === 'paid')>Pagado</option>
                                    <option value="failed" @selected(old('payment_status') === 'failed')>Fallido</option>
                                    <option value="refunded" @selected(old('payment_status') === 'refunded')>Reembolsado</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">IGV (%)</label>
                                <input type="number" step="0.0001" min="0" max="1" name="tax_rate" id="tax_rate" class="form-control" value="{{ old('tax_rate', $defaultTaxRate) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Descuento</label>
                                <input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control" value="{{ old('discount', 0) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Envío</label>
                                <input type="number" step="0.01" min="0" name="shipping" id="shipping" class="form-control" value="{{ old('shipping', 0) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Observaciones</label>
                                <input type="text" name="observations" class="form-control" value="{{ old('observations') }}" placeholder="Notas rápidas para la venta">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 sales-pos-card mb-4">
                    <div class="card-header sales-pos-section-header bg-transparent border-0 pb-0">
                        <div>
                            <h4 class="mb-1">Cliente</h4>
                            <p class="text-muted mb-0" id="customer-help">
                                Para pedidos POS solo se requieren datos básicos. Para boleta o factura completa también el documento.
                            </p>
                        </div>
                    </div>
                    <div class="card-body p-3 p-md-4 pt-3">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Cliente</label>
                                <input type="text" name="customer[name]" class="form-control" value="{{ old('customer.name') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Dirección <span class="text-muted">(opcional)</span></label>
                                <input type="text" name="customer[address]" id="customer_address" class="form-control" value="{{ old('customer.address') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Ciudad <span class="text-muted">(opcional)</span></label>
                                <input type="text" name="customer[city]" id="customer_city" class="form-control" value="{{ old('customer.city') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Teléfono <span class="text-muted">(opcional)</span></label>
                                <input type="text" name="customer[phone]" id="customer_phone" class="form-control" value="{{ old('customer.phone') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Doc. tipo</label>
                                <select name="customer[document_type]" id="customer_document_type" class="form-select">
                                    <option value="">-</option>
                                    <option value="DNI" @selected(old('customer.document_type') === 'DNI')>DNI</option>
                                    <option value="RUC" @selected(old('customer.document_type') === 'RUC')>RUC</option>
                                    <option value="CE" @selected(old('customer.document_type') === 'CE')>CE</option>
                                    <option value="PAS" @selected(old('customer.document_type') === 'PAS')>PAS</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Doc. nro</label>
                                <div class="input-group">
                                    <input type="text" name="customer[document_number]" id="customer_document_number" class="form-control" value="{{ old('customer.document_number') }}">
                                    <button type="button" class="btn btn-light border" id="lookup-document-btn">Consultar</button>
                                </div>
                                <small class="text-muted d-block mt-1" id="lookup-document-feedback">Completa DNI o RUC para consultar el nombre del cliente.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 sales-pos-card">
                    <div class="card-header sales-pos-section-header bg-transparent border-0 pb-0">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                            <div>
                                <h4 class="mb-1">Ítems de venta</h4>
                                <p class="text-muted mb-0">Agrega productos, valida stock y corrige precio sin salir del POS.</p>
                            </div>
                            <button type="button" class="btn btn-primary rounded-pill px-4" id="add-item">
                                <i class="fas fa-plus mr-1"></i> Agregar ítem
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3 p-md-4 pt-3">
                        <div class="sales-product-picker mb-4">
                            <div class="row g-3 align-items-end">
                                <div class="col-lg-8">
                                    <label class="form-label">Buscar producto</label>
                                    <input type="text"
                                           id="product-search"
                                           class="form-control"
                                           list="sales-product-options"
                                           placeholder="Escribe nombre o SKU para agregar rápido">
                                    <datalist id="sales-product-options">
                                        @foreach($products as $product)
                                            <option value="{{ $product->name }} ({{ $product->sku ?: 'SIN-SKU' }})" data-product-id="{{ $product->id }}"></option>
                                        @endforeach
                                    </datalist>
                                </div>
                                <div class="col-lg-4">
                                    <button type="button" class="btn btn-light border rounded-pill px-4 w-100" id="add-item-by-search">
                                        <i class="fas fa-magnifying-glass mr-1"></i> Agregar producto buscado
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">Tip: busca por nombre o SKU y presiona Enter para agregar el producto al detalle.</small>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle sales-items-table" id="items-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-end">Stock</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end">Precio unit.</th>
                                        <th class="text-end">Subtotal</th>
                                        <th style="width: 90px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="item-row">
                                        <td>
                                            <select name="items[0][product_id]" class="form-select product-select" required>
                                                <option value="">Seleccionar...</option>
                                                @foreach($products as $product)
                                                    <option value="{{ $product->id }}" data-price="{{ (float) ($product->sale_price ?? $product->price ?? 0) }}" data-stock="{{ (int) $product->stock }}">
                                                        {{ $product->name }} ({{ $product->sku ?: 'SIN-SKU' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-end stock-cell">0</td>
                                        <td>
                                            <input type="number" min="1" name="items[0][quantity]" class="form-control text-end qty-input" value="1" required>
                                        </td>
                                        <td>
                                            <input type="number" min="0" step="0.01" name="items[0][unit_price]" class="form-control text-end price-input" value="0" required>
                                        </td>
                                        <td class="text-end subtotal-cell">0.00</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill remove-item">Quitar</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card border-0 sales-pos-card sales-pos-summary-card">
                    <div class="card-body p-3 p-md-4">
                        <div class="sales-pos-summary-head mb-3">
                            <div class="sales-pos-eyebrow">Resumen de emisión</div>
                            <h4 class="mb-1" id="summary-document-title">Pedido POS</h4>
                            <p class="text-muted mb-0" id="summary-document-meta">Venta rápida sin comprobante electrónico.</p>
                        </div>

                        <div class="sales-summary-metrics mb-3">
                            <div class="sales-summary-metric">
                                <span>Ítems</span>
                                <strong id="summary-items-count">1</strong>
                            </div>
                            <div class="sales-summary-metric">
                                <span>Pago</span>
                                <strong id="summary-payment-method">Efectivo</strong>
                            </div>
                            <div class="sales-summary-metric">
                                <span>Estado</span>
                                <strong id="summary-payment-status">Pendiente</strong>
                            </div>
                        </div>

                        <div class="sales-summary-totals">
                            <div class="sales-summary-line">
                                <span>Subtotal</span>
                                <strong id="summary-subtotal">0.00</strong>
                            </div>
                            <div class="sales-summary-line">
                                <span>Descuento</span>
                                <strong id="summary-discount">0.00</strong>
                            </div>
                            <div class="sales-summary-line">
                                <span>Envío</span>
                                <strong id="summary-shipping">0.00</strong>
                            </div>
                            <div class="sales-summary-line">
                                <span>IGV</span>
                                <strong id="summary-tax">0.00</strong>
                            </div>
                            <div class="sales-summary-total">
                                <span>Total</span>
                                <strong id="summary-total">0.00</strong>
                            </div>
                        </div>

                        <div class="sales-summary-footer mt-4">
                            <button class="btn btn-primary btn-lg rounded-pill btn-block">Registrar venta</button>
                            <small class="d-block text-muted mt-2">Antes de emitir factura o boleta, verifica documento del cliente y totales.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .sales-pos-card {
        border: 1px solid var(--admin-card-border) !important;
        border-radius: 1rem;
        box-shadow: 0 12px 24px rgba(31, 45, 61, .05);
        background: #fff;
    }

    .sales-pos-hero {
        display: grid;
        gap: 1.25rem;
    }

    .sales-pos-eyebrow {
        display: inline-block;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--admin-primary-button);
    }

    .sales-pos-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: #17283a;
    }

    .sales-pos-doc-switch {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
    }

    .sales-doc-chip {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: .2rem;
        width: 100%;
        padding: 1rem;
        border: 1px solid #dfe5ec;
        border-radius: 1rem;
        background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 100%);
        text-align: left;
        transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
    }

    .sales-doc-chip:hover {
        transform: translateY(-1px);
        border-color: var(--admin-primary-button);
    }

    .sales-doc-chip.is-active {
        border-color: var(--admin-primary-button);
        box-shadow: inset 0 0 0 1px var(--admin-primary-button), 0 10px 20px rgba(31, 45, 61, .06);
        background: linear-gradient(180deg, rgba(255,255,255,1) 0%, rgba(247,249,252,1) 60%, color-mix(in srgb, var(--admin-primary-button) 10%, white) 100%);
    }

    .sales-doc-chip__title {
        font-weight: 700;
        color: #17283a;
    }

    .sales-doc-chip__meta {
        font-size: .85rem;
        color: #6c7a89;
    }

    .sales-pos-section-header h4 {
        font-size: 1.08rem;
        font-weight: 700;
        color: #17283a;
    }

    .sales-items-table thead th {
        background: #f8f9fb;
        border-top: 0;
    }

    .sales-product-picker {
        padding: 1rem;
        border: 1px solid #e4e9ef;
        border-radius: 1rem;
        background: linear-gradient(180deg, #ffffff 0%, #fafbfd 100%);
    }

    .sales-pos-summary-card {
        position: sticky;
        top: 1rem;
        overflow: hidden;
    }

    .sales-pos-summary-card .card-body {
        background:
            radial-gradient(circle at top right, color-mix(in srgb, var(--admin-primary-button) 14%, white) 0, transparent 36%),
            linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
    }

    .sales-summary-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
    }

    .sales-summary-metric {
        padding: .85rem;
        border-radius: .9rem;
        background: #f7f9fc;
        border: 1px solid #e4e9ef;
    }

    .sales-summary-metric span {
        display: block;
        font-size: .8rem;
        color: #6c7a89;
    }

    .sales-summary-metric strong {
        display: block;
        margin-top: .25rem;
        font-size: .98rem;
        color: #17283a;
    }

    .sales-summary-totals {
        border: 1px solid #e4e9ef;
        border-radius: 1rem;
        background: #fff;
        overflow: hidden;
    }

    .sales-summary-line,
    .sales-summary-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .9rem 1rem;
    }

    .sales-summary-line + .sales-summary-line,
    .sales-summary-line + .sales-summary-total {
        border-top: 1px solid #edf1f5;
    }

    .sales-summary-total {
        background: color-mix(in srgb, var(--admin-primary-button) 10%, white);
        font-size: 1.02rem;
    }

    .sales-summary-total strong {
        font-size: 1.3rem;
        color: #17283a;
    }

    @media (max-width: 991.98px) {
        .sales-pos-doc-switch,
        .sales-summary-metrics {
            grid-template-columns: 1fr;
        }

        .sales-pos-summary-card {
            position: static;
        }
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.sales-pos-form');
    const tableBody = document.querySelector('#items-table tbody');
    const addBtn = document.getElementById('add-item');
    const addBySearchBtn = document.getElementById('add-item-by-search');
    const productSearchInput = document.getElementById('product-search');
    const documentTypeInput = document.getElementById('document_type');
    const documentButtons = document.querySelectorAll('[data-doc-type]');
    const paymentMethod = document.getElementById('payment_method');
    const paymentStatus = document.getElementById('payment_status');
    const taxRateInput = document.getElementById('tax_rate');
    const discountInput = document.getElementById('discount');
    const shippingInput = document.getElementById('shipping');
    const customerHelp = document.getElementById('customer-help');
    const customerDocumentType = document.getElementById('customer_document_type');
    const customerDocumentNumber = document.getElementById('customer_document_number');
    const customerNameInput = document.querySelector('input[name="customer[name]"]');
    const customerAddressInput = document.getElementById('customer_address');
    const customerCityInput = document.getElementById('customer_city');
    const customerPhoneInput = document.getElementById('customer_phone');
    const lookupButton = document.getElementById('lookup-document-btn');
    const lookupFeedback = document.getElementById('lookup-document-feedback');
    let index = 1;
    const productIndex = @json($productIndex);
    const lookupEndpoint = @json(route('admin.sales.pos.customer-lookup'));

    const summary = {
        title: document.getElementById('summary-document-title'),
        meta: document.getElementById('summary-document-meta'),
        itemsCount: document.getElementById('summary-items-count'),
        paymentMethod: document.getElementById('summary-payment-method'),
        paymentStatus: document.getElementById('summary-payment-status'),
        subtotal: document.getElementById('summary-subtotal'),
        discount: document.getElementById('summary-discount'),
        shipping: document.getElementById('summary-shipping'),
        tax: document.getElementById('summary-tax'),
        total: document.getElementById('summary-total'),
    };

    const documentTypeMeta = {
        order: {
            title: 'Pedido POS',
            meta: 'Venta rápida sin comprobante electrónico.',
            help: 'Para pedidos POS solo se requieren datos básicos. El documento del cliente es opcional.',
        },
        boleta: {
            title: 'Boleta electrónica',
            meta: 'Se emitirá comprobante electrónico al finalizar la venta.',
            help: 'Para boleta completa el documento del cliente y verifica el número antes de emitir.',
        },
        factura: {
            title: 'Factura electrónica',
            meta: 'Se emitirá factura electrónica con datos tributarios.',
            help: 'Para factura el cliente debe tener documento tipo RUC y número válido.',
        },
    };

    function money(value) {
        return Number(value || 0).toFixed(2);
    }

    function updateRow(row) {
        const select = row.querySelector('.product-select');
        const stockCell = row.querySelector('.stock-cell');
        const qtyInput = row.querySelector('.qty-input');
        const priceInput = row.querySelector('.price-input');
        const subtotalCell = row.querySelector('.subtotal-cell');
        const selected = select.options[select.selectedIndex];
        const stock = Number(selected?.dataset?.stock || 0);
        const defaultPrice = Number(selected?.dataset?.price || 0);

        stockCell.textContent = String(stock);

        if ((!priceInput.value || Number(priceInput.value) <= 0) && defaultPrice > 0) {
            priceInput.value = defaultPrice.toFixed(2);
        }

        const subtotal = Number(qtyInput.value || 0) * Number(priceInput.value || 0);
        subtotalCell.textContent = subtotal.toFixed(2);
        updateSummary();
    }

    function updateSummary() {
        const rows = tableBody.querySelectorAll('.item-row');
        let subtotal = 0;

        rows.forEach((row) => {
            subtotal += Number(row.querySelector('.qty-input')?.value || 0) * Number(row.querySelector('.price-input')?.value || 0);
        });

        const discount = Number(discountInput.value || 0);
        const shipping = Number(shippingInput.value || 0);
        const taxRate = Number(taxRateInput.value || 0);
        const taxable = Math.max(0, subtotal - discount);
        const tax = taxable * taxRate;
        const total = taxable + tax + shipping;

        summary.itemsCount.textContent = String(rows.length);
        summary.paymentMethod.textContent = paymentMethod.options[paymentMethod.selectedIndex]?.text || '-';
        summary.paymentStatus.textContent = paymentStatus.options[paymentStatus.selectedIndex]?.text || '-';
        summary.subtotal.textContent = money(subtotal);
        summary.discount.textContent = money(discount);
        summary.shipping.textContent = money(shipping);
        summary.tax.textContent = money(tax);
        summary.total.textContent = money(total);
    }

    function updateDocumentTypeState() {
        const current = documentTypeInput.value;
        const meta = documentTypeMeta[current] || documentTypeMeta.order;

        documentButtons.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.docType === current);
        });

        summary.title.textContent = meta.title;
        summary.meta.textContent = meta.meta;
        customerHelp.textContent = meta.help;

        if (current === 'factura' && !customerDocumentType.value) {
            customerDocumentType.value = 'RUC';
        }
    }

    function updateLookupHelp(message, isError = false) {
        lookupFeedback.textContent = message;
        lookupFeedback.classList.toggle('text-danger', isError);
        lookupFeedback.classList.toggle('text-muted', !isError);
    }

    function bindRow(row) {
        row.querySelector('.product-select').addEventListener('change', () => updateRow(row));
        row.querySelector('.qty-input').addEventListener('input', () => updateRow(row));
        row.querySelector('.price-input').addEventListener('input', () => updateRow(row));
        row.querySelector('.remove-item').addEventListener('click', () => {
            const rows = tableBody.querySelectorAll('.item-row');
            if (rows.length > 1) {
                row.remove();
                updateSummary();
                return;
            }

            row.querySelector('.product-select').value = '';
            row.querySelector('.qty-input').value = '1';
            row.querySelector('.price-input').value = '0';
            row.querySelector('.stock-cell').textContent = '0';
            row.querySelector('.subtotal-cell').textContent = '0.00';
            updateSummary();
        });
    }

    function createFreshRow() {
        const row = tableBody.querySelector('.item-row').cloneNode(true);

        row.querySelectorAll('select,input').forEach((input) => {
            input.name = input.name.replace(/\[\d+\]/, '[' + index + ']');
        });

        row.querySelector('.product-select').value = '';
        row.querySelector('.qty-input').value = '1';
        row.querySelector('.price-input').value = '0';
        row.querySelector('.stock-cell').textContent = '0';
        row.querySelector('.subtotal-cell').textContent = '0.00';

        tableBody.appendChild(row);
        bindRow(row);
        index++;

        return row;
    }

    function findProductBySearch(value) {
        const normalized = String(value || '').trim().toLowerCase();

        if (!normalized) {
            return null;
        }

        return productIndex.find((product) => {
            return product.label.toLowerCase() === normalized
                || product.name.toLowerCase() === normalized
                || product.sku.toLowerCase() === normalized;
        }) || null;
    }

    function addProductFromSearch() {
        const product = findProductBySearch(productSearchInput.value);
        if (!product) {
            productSearchInput.focus();
            return;
        }

        const row = createFreshRow();
        row.querySelector('.product-select').value = String(product.id);
        row.querySelector('.qty-input').value = '1';
        row.querySelector('.price-input').value = Number(product.price || 0).toFixed(2);
        updateRow(row);
        updateSummary();

        productSearchInput.value = '';
        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    documentButtons.forEach((button) => {
        button.addEventListener('click', () => {
            documentTypeInput.value = button.dataset.docType;
            updateDocumentTypeState();
        });
    });

    [paymentMethod, paymentStatus, taxRateInput, discountInput, shippingInput].forEach((element) => {
        element.addEventListener('input', updateSummary);
        element.addEventListener('change', updateSummary);
    });

    bindRow(tableBody.querySelector('.item-row'));

    addBtn.addEventListener('click', () => {
        createFreshRow();
        updateSummary();
    });

    addBySearchBtn.addEventListener('click', addProductFromSearch);
    productSearchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addProductFromSearch();
        }
    });

    lookupButton.addEventListener('click', async () => {
        const documentType = customerDocumentType.value;
        const documentNumber = customerDocumentNumber.value.trim();

        if (!['DNI', 'RUC'].includes(documentType) || documentNumber === '') {
            updateLookupHelp('Selecciona DNI o RUC y completa el número del documento.', true);
            customerDocumentNumber.focus();
            return;
        }

        lookupButton.disabled = true;
        updateLookupHelp('Consultando documento...');

        try {
            const response = await fetch(lookupEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    document_type: documentType,
                    document_number: documentNumber,
                }),
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                updateLookupHelp(result.message || 'No se pudo consultar el documento.', true);
                return;
            }

            if (result.name && !customerNameInput.value.trim()) {
                customerNameInput.value = result.name;
            } else if (result.name) {
                customerNameInput.value = result.name;
            }

            if (result.address && !customerAddressInput.value.trim()) {
                customerAddressInput.value = result.address;
            }

            if (result.city && !customerCityInput.value.trim()) {
                customerCityInput.value = result.city;
            }

            if (result.phone && !customerPhoneInput.value.trim()) {
                customerPhoneInput.value = result.phone;
            }

            updateLookupHelp('Documento consultado correctamente.');
        } catch (error) {
            updateLookupHelp('Error de conexión al consultar el documento.', true);
        } finally {
            lookupButton.disabled = false;
        }
    });

    form.addEventListener('submit', updateSummary);

    updateDocumentTypeState();
    updateRow(tableBody.querySelector('.item-row'));
    updateSummary();
});
</script>
@endpush

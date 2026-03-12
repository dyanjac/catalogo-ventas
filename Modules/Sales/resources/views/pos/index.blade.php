@extends('layouts.admin')

@section('title', 'Punto de venta')
@section('page_title', 'Punto de venta')

@php
    $defaultDocumentType = old('document_type', 'order');
    $defaultCurrency = old('currency', $defaultCurrency ?? config('sales.default_currency', 'PEN'));
    $defaultTaxRate = old('tax_rate', $defaultTaxRate ?? config('sales.default_tax_rate', 0.18));
    $oldItems = old('items', [
        ['product_id' => '', 'quantity' => 1, 'unit_price' => 0],
    ]);

    $productIndex = collect($products ?? [])->map(function ($product) {
        $price = (float) ($product->sale_price ?? $product->price ?? 0);

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'sku' => (string) ($product->sku ?: 'SIN-SKU'),
            'stock' => (int) ($product->stock ?? 0),
            'price' => round($price, 2),
            'label' => (string) ($product->name.' ('.($product->sku ?: 'SIN-SKU').')'),
        ];
    })->values()->all();
@endphp

@section('content')
    <div class="py-2">
        <x-admin.page-header title="Punto de venta">
            <x-slot:actions>
                <a href="{{ route('admin.billing.documents.index') }}" class="btn btn-light border rounded-pill px-4">
                    Ver docs electronicos
                </a>
            </x-slot:actions>
        </x-admin.page-header>

        <p class="text-muted mb-4">Registra pedido POS, boleta o factura con un flujo guiado.</p>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>No se pudo registrar la venta.</strong>
            <ul class="mb-0 mt-2 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.sales.pos.store') }}" method="POST" id="pos-form" novalidate>
        @csrf
        <input type="hidden" name="document_type" id="document_type" value="{{ $defaultDocumentType }}">

        <div class="card card-outline card-primary pos-shell">
            <div class="card-body">
                <div class="pos-hero">
                    <div>
                        <p class="pos-kicker mb-2">Emision</p>
                        <h2 class="pos-title mb-1" id="summary-document-title">Pedido POS</h2>
                        <p class="text-muted mb-0" id="summary-document-meta">Flujo rapido para registrar y confirmar la venta.</p>
                    </div>
                    <div class="document-switcher" role="tablist" aria-label="Tipo de comprobante">
                        <button type="button" class="document-chip" data-document-type="order">Pedido POS</button>
                        <button type="button" class="document-chip" data-document-type="boleta">Boleta</button>
                        <button type="button" class="document-chip" data-document-type="factura">Factura</button>
                    </div>
                </div>

                <div class="wizard-steps" id="wizard-steps">
                    <button type="button" class="wizard-step" data-step="0">
                        <span class="wizard-step-index">1</span>
                        <span>Productos</span>
                    </button>
                    <button type="button" class="wizard-step" data-step="1">
                        <span class="wizard-step-index">2</span>
                        <span>Cliente</span>
                    </button>
                    <button type="button" class="wizard-step" data-step="2">
                        <span class="wizard-step-index">3</span>
                        <span>Pago y resumen</span>
                    </button>
                </div>

                <div class="wizard-help alert alert-light border mb-4" id="wizard-help">
                    Selecciona los productos primero. Luego completa al cliente y al final confirma el pago.
                </div>

                <div class="sales-wizard-viewport">
                    <div class="sales-wizard-track" id="sales-wizard-track">
                        <section class="wizard-panel">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="wizard-card">
                                        <div class="wizard-card-header">
                                            <div>
                                                <h3>1. Seleccion de productos</h3>
                                                <p>Busca por nombre o SKU y arma la venta antes de completar datos adicionales.</p>
                                            </div>
                                            <button type="button" class="btn btn-primary" id="add-item-row">Agregar item</button>
                                        </div>

                                        <div class="product-search-box">
                                            <label for="product-search" class="font-weight-semibold">Busqueda rapida</label>
                                            <div class="input-group">
                                                <input type="text" id="product-search" class="form-control" list="product-search-list" placeholder="Buscar producto por nombre o SKU">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-primary" id="add-item-by-search">Agregar producto buscado</button>
                                                </div>
                                            </div>
                                            <datalist id="product-search-list">
                                                @foreach ($productIndex as $product)
                                                    <option value="{{ $product['label'] }}"></option>
                                                @endforeach
                                            </datalist>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle" id="items-table">
                                                <thead>
                                                    <tr>
                                                        <th style="min-width: 320px;">Producto</th>
                                                        <th style="width: 90px;">Stock</th>
                                                        <th style="width: 120px;">Cantidad</th>
                                                        <th style="width: 140px;">Precio unit.</th>
                                                        <th style="width: 130px;">Subtotal</th>
                                                        <th style="width: 80px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="items-tbody">
                                                    @foreach ($oldItems as $index => $item)
                                                        <tr class="item-row">
                                                            <td>
                                                                <select name="items[{{ $index }}][product_id]" class="form-control product-select">
                                                                    <option value="">Seleccionar...</option>
                                                                    @foreach ($productIndex as $product)
                                                                        <option
                                                                            value="{{ $product['id'] }}"
                                                                            data-stock="{{ $product['stock'] }}"
                                                                            data-price="{{ $product['price'] }}"
                                                                            @selected((string) ($item['product_id'] ?? '') === (string) $product['id'])
                                                                        >
                                                                            {{ $product['label'] }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                            <td class="stock-cell">0</td>
                                                            <td>
                                                                <input type="number" min="0.01" step="0.01" name="items[{{ $index }}][quantity]" class="form-control qty-input" value="{{ $item['quantity'] ?? 1 }}">
                                                            </td>
                                                            <td>
                                                                <input type="number" min="0" step="0.01" name="items[{{ $index }}][unit_price]" class="form-control price-input" value="{{ $item['unit_price'] ?? 0 }}">
                                                            </td>
                                                            <td class="subtotal-cell">0.00</td>
                                                            <td class="text-right">
                                                                <button type="button" class="btn btn-outline-danger btn-sm remove-item">Quitar</button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="summary-card">
                                        <p class="summary-kicker">Resumen parcial</p>
                                        <div class="summary-line">
                                            <span>Items</span>
                                            <strong id="summary-items-count">0</strong>
                                        </div>
                                        <div class="summary-line">
                                            <span>Subtotal</span>
                                            <strong id="summary-subtotal">0.00</strong>
                                        </div>
                                        <div class="summary-line total">
                                            <span>Total estimado</span>
                                            <strong id="summary-total">0.00</strong>
                                        </div>
                                        <hr>
                                        <p class="summary-caption mb-0">Continua cuando la lista de productos este completa.</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="wizard-panel">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="wizard-card">
                                        <div class="wizard-card-header">
                                            <div>
                                                <h3>2. Informacion del cliente</h3>
                                                <p>Completa solo lo necesario para el tipo de comprobante seleccionado.</p>
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="customer_name">Cliente</label>
                                                <input type="text" class="form-control" id="customer_name" name="customer[name]" value="{{ old('customer.name') }}" placeholder="Nombre o razon social">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label for="customer_document_type">Doc. tipo</label>
                                                <select class="form-control" id="customer_document_type" name="customer[document_type]">
                                                    <option value="">-</option>
                                                    <option value="DNI" @selected(old('customer.document_type') === 'DNI')>DNI</option>
                                                    <option value="RUC" @selected(old('customer.document_type') === 'RUC')>RUC</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label for="customer_document_number">Doc. nro</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="customer_document_number" name="customer[document_number]" value="{{ old('customer.document_number') }}">
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-outline-primary" id="lookup-document-btn">Consultar</button>
                                                    </div>
                                                </div>
                                                <small class="form-text text-muted" id="lookup-document-feedback"></small>
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-5">
                                                <label for="customer_address">Direccion <span class="text-muted">(opcional)</span></label>
                                                <input type="text" class="form-control" id="customer_address" name="customer[address]" value="{{ old('customer.address') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label for="customer_city">Ciudad <span class="text-muted">(opcional)</span></label>
                                                <input type="text" class="form-control" id="customer_city" name="customer[city]" value="{{ old('customer.city') }}">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="customer_phone">Telefono <span class="text-muted">(opcional)</span></label>
                                                <input type="text" class="form-control" id="customer_phone" name="customer[phone]" value="{{ old('customer.phone') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="summary-card">
                                        <p class="summary-kicker">Cliente</p>
                                        <div class="summary-block">
                                            <span class="summary-label">Nombre</span>
                                            <strong id="summary-customer-name">Sin definir</strong>
                                        </div>
                                        <div class="summary-block">
                                            <span class="summary-label">Documento</span>
                                            <strong>
                                                <span id="summary-customer-doc-type">-</span>
                                                <span id="summary-customer-doc-number"></span>
                                            </strong>
                                        </div>
                                        <hr>
                                        <p class="summary-caption mb-0" id="customer-step-note">
                                            Para factura el cliente debe tener documento tipo RUC valido.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="wizard-panel">
                            <div class="row">
                                <div class="col-lg-7">
                                    <div class="wizard-card">
                                        <div class="wizard-card-header">
                                            <div>
                                                <h3>3. Pago y cierre</h3>
                                                <p>Ajusta condiciones finales y confirma el registro.</p>
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-4">
                                                <label for="currency">Moneda</label>
                                                <select class="form-control" id="currency" name="currency">
                                                    <option value="PEN" @selected($defaultCurrency === 'PEN')>PEN</option>
                                                    <option value="USD" @selected($defaultCurrency === 'USD')>USD</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="payment_method">Metodo pago</label>
                                                <select class="form-control" id="payment_method" name="payment_method">
                                                    <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Efectivo</option>
                                                    <option value="card" @selected(old('payment_method') === 'card')>Tarjeta</option>
                                                    <option value="transfer" @selected(old('payment_method') === 'transfer')>Transferencia</option>
                                                    <option value="credit" @selected(old('payment_method') === 'credit')>Credito</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="payment_status">Estado pago</label>
                                                <select class="form-control" id="payment_status" name="payment_status">
                                                    <option value="pending" @selected(old('payment_status', 'pending') === 'pending')>Pendiente</option>
                                                    <option value="paid" @selected(old('payment_status') === 'paid')>Pagado</option>
                                                    <option value="partial" @selected(old('payment_status') === 'partial')>Parcial</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-4">
                                                <label for="tax_rate">IGV (%)</label>
                                                <input type="number" min="0" step="0.01" class="form-control" id="tax_rate" name="tax_rate" value="{{ $defaultTaxRate }}">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="discount">Descuento</label>
                                                <input type="number" min="0" step="0.01" class="form-control" id="discount" name="discount" value="{{ old('discount', 0) }}">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="shipping">Envio</label>
                                                <input type="number" min="0" step="0.01" class="form-control" id="shipping" name="shipping" value="{{ old('shipping', 0) }}">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="observations">Observaciones</label>
                                            <textarea class="form-control" id="observations" name="observations" rows="3" placeholder="Notas rapidas para la venta">{{ old('observations') }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-5">
                                    <div class="summary-card summary-card-strong">
                                        <p class="summary-kicker">Cierre de venta</p>
                                        <div class="summary-line">
                                            <span>Documento</span>
                                            <strong id="summary-document-title-final">Pedido POS</strong>
                                        </div>
                                        <div class="summary-line">
                                            <span>Cliente</span>
                                            <strong id="summary-customer-name-final">Sin definir</strong>
                                        </div>
                                        <div class="summary-line">
                                            <span>Items</span>
                                            <strong id="summary-items-count-final">0</strong>
                                        </div>
                                        <div class="summary-line">
                                            <span>Metodo pago</span>
                                            <strong id="summary-payment-method">Efectivo</strong>
                                        </div>
                                        <div class="summary-line">
                                            <span>Estado pago</span>
                                            <strong id="summary-payment-status">Pendiente</strong>
                                        </div>
                                        <hr>
                                        <div class="summary-line">
                                            <span>Subtotal</span>
                                            <strong id="summary-subtotal-final">0.00</strong>
                                        </div>
                                        <div class="summary-line">
                                            <span>Descuento</span>
                                            <strong id="summary-discount">0.00</strong>
                                        </div>
                                        <div class="summary-line">
                                            <span>Envio</span>
                                            <strong id="summary-shipping">0.00</strong>
                                        </div>
                                        <div class="summary-line">
                                            <span>IGV</span>
                                            <strong id="summary-tax">0.00</strong>
                                        </div>
                                        <div class="summary-line total">
                                            <span>Total</span>
                                            <strong id="summary-total-final">0.00</strong>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-block btn-lg mt-4">Registrar venta</button>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="wizard-nav mt-4">
                    <button type="button" class="btn btn-outline-secondary" id="wizard-prev">Anterior</button>
                    <button type="button" class="btn btn-primary" id="wizard-next">Continuar</button>
                </div>
            </div>
        </div>
    </form>
    </div>
@stop

@push('styles')
    <style>
        .pos-shell { border-radius: 20px; }
        .pos-hero {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        .pos-kicker,
        .summary-kicker {
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: .75rem;
            font-weight: 700;
            color: #6c7a89;
        }
        .pos-title {
            font-size: 2rem;
            font-weight: 700;
        }
        .document-switcher {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .document-chip {
            border: 1px solid #dbe1ea;
            background: #fff;
            color: #334155;
            border-radius: 999px;
            padding: .7rem 1rem;
            font-weight: 600;
            min-width: 132px;
        }
        .document-chip.is-active {
            background: linear-gradient(135deg, #0d6efd, #3f8cff);
            border-color: #0d6efd;
            color: #fff;
            box-shadow: 0 10px 24px rgba(13, 110, 253, .18);
        }
        .wizard-steps {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .wizard-step {
            border: 1px solid #dbe1ea;
            background: #fff;
            border-radius: 16px;
            padding: .9rem 1rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            font-weight: 600;
            color: #334155;
            justify-content: flex-start;
        }
        .wizard-step.is-active,
        .wizard-step.is-complete {
            border-color: #0d6efd;
            background: rgba(13, 110, 253, .08);
        }
        .wizard-step-index {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #eef2f7;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .wizard-step.is-active .wizard-step-index,
        .wizard-step.is-complete .wizard-step-index {
            background: #0d6efd;
            color: #fff;
        }
        .sales-wizard-viewport { overflow: hidden; }
        .sales-wizard-track {
            display: flex;
            width: 300%;
            transition: transform .35s ease;
        }
        .wizard-panel {
            width: 33.333333%;
            flex: 0 0 33.333333%;
            padding-right: .75rem;
        }
        .wizard-card,
        .summary-card {
            border: 1px solid #e4e8ef;
            border-radius: 18px;
            background: #fff;
            padding: 1.25rem;
            height: 100%;
        }
        .wizard-card-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .wizard-card-header h3 {
            margin: 0 0 .25rem 0;
            font-size: 1.25rem;
            font-weight: 700;
        }
        .wizard-card-header p,
        .summary-caption { color: #6c7a89; }
        .product-search-box {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #e8edf3;
        }
        .summary-card {
            position: sticky;
            top: 1rem;
        }
        .summary-card-strong { background: linear-gradient(180deg, #ffffff, #f8fbff); }
        .summary-line {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            margin-bottom: .85rem;
        }
        .summary-line.total {
            font-size: 1.15rem;
            font-weight: 700;
        }
        .summary-block { margin-bottom: 1rem; }
        .summary-label {
            display: block;
            color: #6c7a89;
            font-size: .85rem;
            margin-bottom: .2rem;
        }
        .wizard-nav {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }
        @media (max-width: 991.98px) {
            .pos-hero,
            .wizard-card-header { flex-direction: column; }
            .document-switcher {
                width: 100%;
                justify-content: flex-start;
            }
            .wizard-steps { grid-template-columns: 1fr; }
            .wizard-panel { padding-right: 0; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const productIndex = @json($productIndex);
            const documentInput = document.getElementById('document_type');
            const documentButtons = Array.from(document.querySelectorAll('.document-chip'));
            const wizardTrack = document.getElementById('sales-wizard-track');
            const wizardSteps = Array.from(document.querySelectorAll('.wizard-step'));
            const wizardHelp = document.getElementById('wizard-help');
            const prevButton = document.getElementById('wizard-prev');
            const nextButton = document.getElementById('wizard-next');
            const itemsTbody = document.getElementById('items-tbody');
            const addItemRowButton = document.getElementById('add-item-row');
            const addItemBySearchButton = document.getElementById('add-item-by-search');
            const productSearch = document.getElementById('product-search');
            const lookupButton = document.getElementById('lookup-document-btn');
            const lookupFeedback = document.getElementById('lookup-document-feedback');
            const customerName = document.getElementById('customer_name');
            const customerDocumentType = document.getElementById('customer_document_type');
            const customerDocumentNumber = document.getElementById('customer_document_number');
            const customerAddress = document.getElementById('customer_address');
            const customerCity = document.getElementById('customer_city');
            const customerPhone = document.getElementById('customer_phone');
            const paymentMethodInput = document.getElementById('payment_method');
            const paymentStatusInput = document.getElementById('payment_status');
            const taxRateInput = document.getElementById('tax_rate');
            const discountInput = document.getElementById('discount');
            const shippingInput = document.getElementById('shipping');

            let currentStep = 0;
            let rowIndex = {{ count($oldItems) }};

            const documentTypeMeta = {
                order: {
                    title: 'Pedido POS',
                    help: 'Registro interno rapido. Puedes completar cliente y pago despues de seleccionar productos.',
                    customer: 'Para pedido POS basta con un nombre de cliente.',
                },
                boleta: {
                    title: 'Boleta electronica',
                    help: 'La boleta necesita cliente y documento valido antes de registrarse.',
                    customer: 'Para boleta registra tipo y numero de documento del cliente.',
                },
                factura: {
                    title: 'Factura electronica',
                    help: 'La factura exige RUC y datos validos del cliente para emitir el comprobante.',
                    customer: 'Para factura el cliente debe tener documento tipo RUC y numero valido.',
                }
            };

            function formatMoney(value) {
                const amount = Number(value || 0);
                return amount.toFixed(2);
            }

            function getProductById(id) {
                return productIndex.find(product => String(product.id) === String(id));
            }

            function getProductBySearchTerm(term) {
                const normalized = String(term || '').trim().toLowerCase();
                if (!normalized) {
                    return null;
                }

                return productIndex.find(product =>
                    product.label.toLowerCase() === normalized ||
                    product.name.toLowerCase().includes(normalized) ||
                    product.sku.toLowerCase().includes(normalized)
                ) || null;
            }

            function buildProductOptions(selectedId) {
                const options = ['<option value="">Seleccionar...</option>'];

                productIndex.forEach(function (product) {
                    const selected = String(selectedId || '') === String(product.id) ? ' selected' : '';
                    options.push(
                        '<option value="' + product.id + '" data-stock="' + product.stock + '" data-price="' + product.price + '"' + selected + '>' +
                        product.label +
                        '</option>'
                    );
                });

                return options.join('');
            }

            function createItemRow(selectedProductId, quantity, unitPrice) {
                const row = document.createElement('tr');
                row.className = 'item-row';
                row.innerHTML = `
                    <td>
                        <select name="items[${rowIndex}][product_id]" class="form-control product-select">
                            ${buildProductOptions(selectedProductId)}
                        </select>
                    </td>
                    <td class="stock-cell">0</td>
                    <td>
                        <input type="number" min="0.01" step="0.01" name="items[${rowIndex}][quantity]" class="form-control qty-input" value="${quantity}">
                    </td>
                    <td>
                        <input type="number" min="0" step="0.01" name="items[${rowIndex}][unit_price]" class="form-control price-input" value="${unitPrice}">
                    </td>
                    <td class="subtotal-cell">0.00</td>
                    <td class="text-right">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-item">Quitar</button>
                    </td>
                `;

                itemsTbody.appendChild(row);
                rowIndex += 1;
                updateRow(row);
                updateSummary();
                return row;
            }

            function ensureAtLeastOneRow() {
                if (!itemsTbody.querySelector('.item-row')) {
                    createItemRow('', 1, 0);
                }
            }

            function updateRow(row) {
                const select = row.querySelector('.product-select');
                const qtyInput = row.querySelector('.qty-input');
                const priceInput = row.querySelector('.price-input');
                const stockCell = row.querySelector('.stock-cell');
                const subtotalCell = row.querySelector('.subtotal-cell');
                const product = getProductById(select.value);
                const qty = Number(qtyInput.value || 0);

                if (product) {
                    stockCell.textContent = product.stock;
                    if (!Number(priceInput.value)) {
                        priceInput.value = product.price;
                    }
                } else {
                    stockCell.textContent = '0';
                }

                subtotalCell.textContent = formatMoney(qty * Number(priceInput.value || 0));
            }

            function updateSummary() {
                let itemCount = 0;
                let subtotal = 0;

                itemsTbody.querySelectorAll('.item-row').forEach(function (row) {
                    updateRow(row);
                    const productId = row.querySelector('.product-select').value;
                    const qty = Number(row.querySelector('.qty-input').value || 0);
                    const price = Number(row.querySelector('.price-input').value || 0);

                    if (productId) {
                        itemCount += qty;
                        subtotal += qty * price;
                    }
                });

                const discount = Number(discountInput.value || 0);
                const shipping = Number(shippingInput.value || 0);
                const rate = Number(taxRateInput.value || 0);
                const base = Math.max(subtotal - discount, 0);
                const tax = base * rate;
                const total = base + tax + shipping;
                const customerNameValue = customerName.value.trim() || 'Sin definir';

                document.getElementById('summary-items-count').textContent = formatMoney(itemCount);
                document.getElementById('summary-items-count-final').textContent = formatMoney(itemCount);
                document.getElementById('summary-subtotal').textContent = formatMoney(subtotal);
                document.getElementById('summary-subtotal-final').textContent = formatMoney(subtotal);
                document.getElementById('summary-discount').textContent = formatMoney(discount);
                document.getElementById('summary-shipping').textContent = formatMoney(shipping);
                document.getElementById('summary-tax').textContent = formatMoney(tax);
                document.getElementById('summary-total').textContent = formatMoney(total);
                document.getElementById('summary-total-final').textContent = formatMoney(total);
                document.getElementById('summary-customer-name').textContent = customerNameValue;
                document.getElementById('summary-customer-name-final').textContent = customerNameValue;
                document.getElementById('summary-customer-doc-type').textContent = customerDocumentType.value || '-';
                document.getElementById('summary-customer-doc-number').textContent = customerDocumentNumber.value ? ' ' + customerDocumentNumber.value : '';
                document.getElementById('summary-payment-method').textContent = paymentMethodInput.options[paymentMethodInput.selectedIndex].text;
                document.getElementById('summary-payment-status').textContent = paymentStatusInput.options[paymentStatusInput.selectedIndex].text;
            }

            function updateDocumentTypeState() {
                const type = documentInput.value;
                const meta = documentTypeMeta[type] || documentTypeMeta.order;

                documentButtons.forEach(function (button) {
                    button.classList.toggle('is-active', button.dataset.documentType === type);
                });

                document.getElementById('summary-document-title').textContent = meta.title;
                document.getElementById('summary-document-title-final').textContent = meta.title;
                document.getElementById('summary-document-meta').textContent = meta.help;
                wizardHelp.textContent = meta.help;
                document.getElementById('customer-step-note').textContent = meta.customer;

                if (type === 'factura') {
                    customerDocumentType.value = 'RUC';
                }

                updateSummary();
            }

            function hasSelectedProducts() {
                return Array.from(itemsTbody.querySelectorAll('.product-select')).some(select => select.value);
            }

            function validateCurrentStep() {
                if (currentStep === 0 && !hasSelectedProducts()) {
                    alert('Debes seleccionar al menos un producto antes de continuar.');
                    return false;
                }

                if (currentStep === 1) {
                    const customerNameValue = customerName.value.trim();
                    const type = documentInput.value;
                    const documentType = customerDocumentType.value;
                    const documentNumber = customerDocumentNumber.value.trim();

                    if (!customerNameValue) {
                        alert('Ingresa el nombre del cliente para continuar.');
                        return false;
                    }

                    if (type === 'factura' && (documentType !== 'RUC' || documentNumber.length !== 11)) {
                        alert('Para factura debes registrar un RUC valido de 11 digitos.');
                        return false;
                    }

                    if (type === 'boleta' && (!documentType || !documentNumber)) {
                        alert('Para boleta registra tipo y numero de documento del cliente.');
                        return false;
                    }
                }

                return true;
            }

            function updateWizardUi() {
                wizardTrack.style.transform = 'translateX(-' + (currentStep * 33.333333) + '%)';

                wizardSteps.forEach(function (stepButton, stepIndex) {
                    stepButton.classList.toggle('is-active', stepIndex === currentStep);
                    stepButton.classList.toggle('is-complete', stepIndex < currentStep);
                });

                prevButton.disabled = currentStep === 0;
                nextButton.textContent = currentStep === 2 ? 'Listo para registrar' : 'Continuar';
            }

            function goToStep(targetStep) {
                if (targetStep > currentStep && !validateCurrentStep()) {
                    return;
                }

                currentStep = Math.max(0, Math.min(2, targetStep));
                updateWizardUi();
            }

            async function lookupDocument() {
                const type = customerDocumentType.value;
                const number = customerDocumentNumber.value.trim();

                if (!type || !number) {
                    lookupFeedback.textContent = 'Selecciona tipo y numero de documento antes de consultar.';
                    lookupFeedback.className = 'form-text text-danger';
                    return;
                }

                lookupButton.disabled = true;
                lookupFeedback.textContent = 'Consultando...';
                lookupFeedback.className = 'form-text text-muted';

                try {
                    const response = await fetch(@json(route('admin.sales.pos.customer-lookup')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @json(csrf_token()),
                        },
                        body: JSON.stringify({
                            document_type: type,
                            document_number: number,
                        }),
                    });

                    const payload = await response.json();

                    if (!response.ok || !payload.ok) {
                        throw new Error(payload.message || 'No se pudo consultar el documento.');
                    }

                    const customer = payload.customer || {};
                    const rawContent = payload.raw?.Contenido || {};
                    const resolvedName = customer.name
                        || payload.name
                        || rawContent.nombrecompleto
                        || [
                            rawContent.prenombres,
                            rawContent.apPrimer,
                            rawContent.apSegundo,
                        ].filter(Boolean).join(' ').trim();
                    const resolvedAddress = customer.address || payload.address || rawContent.direccion;
                    const resolvedCity = customer.city || payload.city || rawContent.ubigeo;
                    const resolvedPhone = customer.phone || payload.phone || rawContent.telefono || rawContent.celular;

                    customerName.value = resolvedName || customerName.value;
                    customerAddress.value = resolvedAddress || customerAddress.value;
                    customerCity.value = resolvedCity || customerCity.value;
                    customerPhone.value = resolvedPhone || customerPhone.value;
                    lookupFeedback.textContent = payload.message || 'Documento consultado correctamente.';
                    lookupFeedback.className = 'form-text text-success';
                    updateSummary();
                } catch (error) {
                    lookupFeedback.textContent = error.message;
                    lookupFeedback.className = 'form-text text-danger';
                } finally {
                    lookupButton.disabled = false;
                }
            }

            itemsTbody.addEventListener('change', function (event) {
                const row = event.target.closest('.item-row');
                if (!row) {
                    return;
                }

                if (event.target.classList.contains('product-select')) {
                    const product = getProductById(event.target.value);
                    if (product) {
                        row.querySelector('.price-input').value = product.price;
                    }
                }

                updateRow(row);
                updateSummary();
            });

            itemsTbody.addEventListener('input', function (event) {
                const row = event.target.closest('.item-row');
                if (!row) {
                    return;
                }

                updateRow(row);
                updateSummary();
            });

            itemsTbody.addEventListener('click', function (event) {
                if (!event.target.classList.contains('remove-item')) {
                    return;
                }

                const rows = itemsTbody.querySelectorAll('.item-row');
                if (rows.length === 1) {
                    rows[0].querySelector('.product-select').value = '';
                    rows[0].querySelector('.qty-input').value = 1;
                    rows[0].querySelector('.price-input').value = 0;
                    updateRow(rows[0]);
                } else {
                    event.target.closest('.item-row').remove();
                }

                ensureAtLeastOneRow();
                updateSummary();
            });

            addItemRowButton.addEventListener('click', function () {
                createItemRow('', 1, 0);
            });

            addItemBySearchButton.addEventListener('click', function () {
                const product = getProductBySearchTerm(productSearch.value);
                if (!product) {
                    alert('No se encontro un producto con ese criterio.');
                    return;
                }

                createItemRow(product.id, 1, product.price);
                productSearch.value = '';
            });

            productSearch.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addItemBySearchButton.click();
                }
            });

            documentButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    documentInput.value = button.dataset.documentType;
                    updateDocumentTypeState();
                });
            });

            wizardSteps.forEach(function (stepButton) {
                stepButton.addEventListener('click', function () {
                    const targetStep = Number(stepButton.dataset.step);
                    if (targetStep <= currentStep || validateCurrentStep()) {
                        goToStep(targetStep);
                    }
                });
            });

            prevButton.addEventListener('click', function () {
                goToStep(currentStep - 1);
            });

            nextButton.addEventListener('click', function () {
                if (currentStep === 2) {
                    if (validateCurrentStep()) {
                        document.getElementById('pos-form').requestSubmit();
                    }
                    return;
                }

                goToStep(currentStep + 1);
            });

            lookupButton.addEventListener('click', lookupDocument);

            [
                customerName,
                customerDocumentType,
                customerDocumentNumber,
                paymentMethodInput,
                paymentStatusInput,
                taxRateInput,
                discountInput,
                shippingInput
            ].forEach(function (element) {
                element.addEventListener('input', updateSummary);
                element.addEventListener('change', updateSummary);
            });

            itemsTbody.querySelectorAll('.item-row').forEach(function (row) {
                updateRow(row);
            });

            updateDocumentTypeState();
            updateSummary();
            updateWizardUi();
        });
    </script>
@endpush

@php
    $meta = $this->documentMeta();
@endphp

<div class="space-y-6">
    <x-admin.page-header
        title="Punto de venta"
        description="Registra pedido POS, boleta o factura con un flujo guiado."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.billing.documents.index') }}" variant="outline" icon="document-text">
                Ver docs electronicos
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.sales.pos.store') }}" method="POST" novalidate>
        @csrf
        <input type="hidden" name="document_type" value="{{ $documentType }}">

        <div class="card border-0 pos-shell">
            <div class="card-body space-y-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <p class="pos-kicker mb-2">Emision</p>
                        <h2 class="pos-title mb-1">{{ $meta['title'] }}</h2>
                        <p class="mb-0 text-muted">{{ $meta['help'] }}</p>
                    </div>

                    <div class="document-switcher">
                        @foreach (['order' => 'Pedido POS', 'boleta' => 'Boleta', 'factura' => 'Factura'] as $type => $label)
                            <button
                                type="button"
                                wire:click="setDocumentType('{{ $type }}')"
                                @class(['document-chip', 'is-active' => $documentType === $type])
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="wizard-steps">
                    @foreach ([0 => 'Productos', 1 => 'Cliente', 2 => 'Pago y resumen'] as $step => $label)
                        <button
                            type="button"
                            wire:click="goToStep({{ $step }})"
                            @class([
                                'wizard-step',
                                'is-active' => $currentStep === $step,
                                'is-complete' => $currentStep > $step,
                            ])
                        >
                            <span class="wizard-step-index">{{ $step + 1 }}</span>
                            <span>{{ $label }}</span>
                        </button>
                    @endforeach
                </div>

                @error('wizard')
                    <div class="alert alert-danger mb-0">{{ $message }}</div>
                @enderror

                <div class="alert alert-light border mb-0">
                    {{ $meta['help'] }}
                </div>

                @if ($currentStep === 0)
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="wizard-card">
                                <div class="wizard-card-header">
                                    <div>
                                        <h3>1. Seleccion de productos</h3>
                                        <p>Busca por nombre o SKU y arma la venta antes de completar datos adicionales.</p>
                                    </div>
                                    <button type="button" class="btn btn-primary" wire:click="addItem">Agregar item</button>
                                </div>

                                <div class="product-search-box">
                                    <label for="product_search" class="font-weight-semibold">Busqueda rapida</label>
                                    <div class="input-group">
                                        <input wire:model.live="productSearch" type="text" id="product_search" class="form-control" list="product-search-list" placeholder="Buscar producto por nombre o SKU">
                                        <button type="button" class="btn btn-outline-primary" wire:click="addItemBySearch">Agregar producto buscado</button>
                                    </div>
                                    <datalist id="product-search-list">
                                        @foreach ($productIndex as $product)
                                            <option value="{{ $product['label'] }}"></option>
                                        @endforeach
                                    </datalist>
                                    @error('productSearch')
                                        <small class="text-danger d-block mt-2">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
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
                                        <tbody>
                                            @foreach ($items as $index => $item)
                                                @php
                                                    $product = collect($productIndex)->firstWhere('id', (int) ($item['product_id'] ?: 0));
                                                    $lineSubtotal = ((float) $item['quantity']) * ((float) $item['unit_price']);
                                                @endphp
                                                <tr wire:key="pos-item-{{ $index }}">
                                                    <td>
                                                        <select
                                                            wire:model.live="items.{{ $index }}.product_id"
                                                            name="items[{{ $index }}][product_id]"
                                                            class="form-control product-select"
                                                        >
                                                            <option value="">Seleccionar...</option>
                                                            @foreach ($productIndex as $option)
                                                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>{{ $product['stock'] ?? 0 }}</td>
                                                    <td>
                                                        <input
                                                            wire:model.live="items.{{ $index }}.quantity"
                                                            type="number"
                                                            min="0.01"
                                                            step="0.01"
                                                            name="items[{{ $index }}][quantity]"
                                                            class="form-control"
                                                        >
                                                    </td>
                                                    <td>
                                                        <input
                                                            wire:model.live="items.{{ $index }}.unit_price"
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            name="items[{{ $index }}][unit_price]"
                                                            class="form-control"
                                                        >
                                                    </td>
                                                    <td>{{ number_format($lineSubtotal, 2) }}</td>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-outline-danger btn-sm" wire:click="removeItem({{ $index }})">
                                                            Quitar
                                                        </button>
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
                                    <strong>{{ number_format($this->itemCount(), 2) }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Subtotal</span>
                                    <strong>{{ number_format($this->subtotal(), 2) }}</strong>
                                </div>
                                <div class="summary-line total">
                                    <span>Total estimado</span>
                                    <strong>{{ number_format($this->totalAmount(), 2) }}</strong>
                                </div>
                                <hr>
                                <p class="summary-caption mb-0">Continua cuando la lista de productos este completa.</p>
                            </div>
                        </div>
                    </div>
                @elseif ($currentStep === 1)
                    <div class="row g-4">
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
                                        <input wire:model.live="customer.name" type="text" class="form-control" id="customer_name" name="customer[name]" placeholder="Nombre o razon social">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="customer_document_type">Doc. tipo</label>
                                        <select wire:model.live="customer.document_type" class="form-control" id="customer_document_type" name="customer[document_type]">
                                            <option value="">-</option>
                                            <option value="DNI">DNI</option>
                                            <option value="RUC">RUC</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="customer_document_number">Doc. nro</label>
                                        <div class="input-group">
                                            <input wire:model.live="customer.document_number" type="text" class="form-control" id="customer_document_number" name="customer[document_number]">
                                            <button type="button" class="btn btn-outline-primary" wire:click="lookupCustomerDocument" wire:loading.attr="disabled">
                                                Consultar
                                            </button>
                                        </div>
                                        @if ($lookupFeedback !== '')
                                            <small class="d-block mt-2 text-{{ $lookupFeedbackType }}">{{ $lookupFeedback }}</small>
                                        @endif
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-5">
                                        <label for="customer_address">Direccion <span class="text-muted">(opcional)</span></label>
                                        <input wire:model.live="customer.address" type="text" class="form-control" id="customer_address" name="customer[address]">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="customer_city">Ciudad <span class="text-muted">(opcional)</span></label>
                                        <input wire:model.live="customer.city" type="text" class="form-control" id="customer_city" name="customer[city]">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="customer_phone">Telefono <span class="text-muted">(opcional)</span></label>
                                        <input wire:model.live="customer.phone" type="text" class="form-control" id="customer_phone" name="customer[phone]">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="summary-card">
                                <p class="summary-kicker">Cliente</p>
                                <div class="summary-block">
                                    <span class="summary-label">Nombre</span>
                                    <strong>{{ $customer['name'] ?: 'Sin definir' }}</strong>
                                </div>
                                <div class="summary-block">
                                    <span class="summary-label">Documento</span>
                                    <strong>{{ ($customer['document_type'] ?: '-') . (($customer['document_number'] ?? '') !== '' ? ' '.$customer['document_number'] : '') }}</strong>
                                </div>
                                <hr>
                                <p class="summary-caption mb-0">{{ $meta['customer'] }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="row g-4">
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
                                        <select wire:model.live="currency" class="form-control" id="currency" name="currency">
                                            <option value="PEN">PEN</option>
                                            <option value="USD">USD</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="payment_method">Metodo pago</label>
                                        <select wire:model.live="paymentMethod" class="form-control" id="payment_method" name="payment_method">
                                            <option value="cash">Efectivo</option>
                                            <option value="card">Tarjeta</option>
                                            <option value="transfer">Transferencia</option>
                                            <option value="yape">Yape</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="payment_status">Estado pago</label>
                                        <select wire:model.live="paymentStatus" class="form-control" id="payment_status" name="payment_status">
                                            <option value="pending">Pendiente</option>
                                            <option value="paid">Pagado</option>
                                            <option value="failed">Fallido</option>
                                            <option value="refunded">Reembolsado</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="tax_rate">IGV (%)</label>
                                        <input wire:model.live="taxRate" type="number" min="0" step="0.01" class="form-control" id="tax_rate" name="tax_rate">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="discount">Descuento</label>
                                        <input wire:model.live="discount" type="number" min="0" step="0.01" class="form-control" id="discount" name="discount">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="shipping">Envio</label>
                                        <input wire:model.live="shipping" type="number" min="0" step="0.01" class="form-control" id="shipping" name="shipping">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="observations">Observaciones</label>
                                    <textarea wire:model.live="observations" class="form-control" id="observations" name="observations" rows="3" placeholder="Notas rapidas para la venta"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="summary-card summary-card-strong">
                                <p class="summary-kicker">Cierre de venta</p>
                                <div class="summary-line">
                                    <span>Documento</span>
                                    <strong>{{ $meta['title'] }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Cliente</span>
                                    <strong>{{ $customer['name'] ?: 'Sin definir' }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Items</span>
                                    <strong>{{ number_format($this->itemCount(), 2) }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Metodo pago</span>
                                    <strong>{{ ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'yape' => 'Yape'][$paymentMethod] ?? $paymentMethod }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Estado pago</span>
                                    <strong>{{ ['pending' => 'Pendiente', 'paid' => 'Pagado', 'failed' => 'Fallido', 'refunded' => 'Reembolsado'][$paymentStatus] ?? $paymentStatus }}</strong>
                                </div>
                                <hr>
                                <div class="summary-line">
                                    <span>Subtotal</span>
                                    <strong>{{ number_format($this->subtotal(), 2) }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Descuento</span>
                                    <strong>{{ number_format($discount, 2) }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Envio</span>
                                    <strong>{{ number_format($shipping, 2) }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>IGV</span>
                                    <strong>{{ number_format($this->taxAmount(), 2) }}</strong>
                                </div>
                                <div class="summary-line total">
                                    <span>Total</span>
                                    <strong>{{ number_format($this->totalAmount(), 2) }}</strong>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block btn-lg mt-4">Registrar venta</button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="wizard-nav">
                    <button type="button" class="btn btn-outline-secondary" wire:click="goPrev" @disabled($currentStep === 0)>
                        Anterior
                    </button>

                    @if ($currentStep < 2)
                        <button type="button" class="btn btn-primary" wire:click="goNext">Continuar</button>
                    @endif
                </div>
            </div>
        </div>
    </form>
</div>

@once
    @push('styles')
        <style>
            .pos-shell { border-radius: 20px; }
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
                .wizard-card-header { flex-direction: column; }
                .document-switcher {
                    width: 100%;
                    justify-content: flex-start;
                }
                .wizard-steps { grid-template-columns: 1fr; }
            }
        </style>
    @endpush
@endonce

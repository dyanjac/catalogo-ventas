@php
    $meta = $this->documentMeta();
@endphp

<div class="space-y-6 pos-screen">
    <x-admin.page-header
        class="pos-page-header"
        title="Punto de venta"
        description="Registra pedido POS, boleta o factura con un flujo guiado."
    >
        <x-slot:actions>
            <flux:button wire:navigate href="{{ route('admin.billing.documents.index') }}" variant="outline" icon="document-text">
                Ver docs electronicos
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.sales.pos.store') }}" method="POST" novalidate>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <input type="hidden" name="document_type" value="{{ $documentType }}">
        <input type="hidden" name="branch_id" value="{{ $branchId }}">
        <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">
        <input type="hidden" name="customer[name]" value="{{ $customer['name'] }}">
        <input type="hidden" name="customer[address]" value="{{ $customer['address'] }}">
        <input type="hidden" name="customer[city]" value="{{ $customer['city'] }}">
        <input type="hidden" name="customer[phone]" value="{{ $customer['phone'] }}">
        <input type="hidden" name="customer[document_type]" value="{{ $customer['document_type'] }}">
        <input type="hidden" name="customer[document_number]" value="{{ $customer['document_number'] }}">

        @foreach ($items as $index => $item)
            <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item['product_id'] }}">
            <input type="hidden" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] }}">
            <input type="hidden" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] }}">
        @endforeach

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
                                aria-pressed="{{ $documentType === $type ? 'true' : 'false' }}"
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
                            aria-current="{{ $currentStep === $step ? 'step' : 'false' }}"
                        >
                            <span class="wizard-step-index">{{ $step + 1 }}</span>
                            <span>{{ $label }}</span>
                        </button>
                    @endforeach
                </div>

                @error('wizard')
                    <div class="alert alert-danger mb-0">{{ $message }}</div>
                @enderror

                    <div class="row g-4 pos-workspace" wire:key="pos-step-products" @if ($currentStep !== 0) hidden @endif>
                        <div class="col-lg-8 pos-workspace__primary">
                            <div class="wizard-card">
                                <div class="wizard-card-header">
                                    <div>
                                        <h3>1. Seleccion de productos</h3>
                                        <p>Busca por nombre o SKU y arma la venta antes de completar datos adicionales.</p>
                                    </div>
                                    <button type="button" class="btn btn-primary" wire:click="addItem">Agregar ítem</button>
                                </div>

                                <div class="product-search-box">
                                    <div class="pos-location-fields">
                                        <div>
                                            <label for="pos_branch">Sucursal</label>
                                            <select id="pos_branch" class="form-control" wire:model.live="branchId">
                                                @foreach ($saleBranches as $branch)
                                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="pos_warehouse">Almacén de salida</label>
                                            <select id="pos_warehouse" class="form-control" wire:model.live="warehouseId">
                                                <option value="">Seleccionar almacén</option>
                                                @foreach ($saleWarehouses as $warehouse)
                                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    @error('branch_id') <small class="text-danger d-block mb-2">{{ $message }}</small> @enderror
                                    @error('warehouse_id') <small class="text-danger d-block mb-2">{{ $message }}</small> @enderror
                                    <label for="product_search" class="font-weight-semibold">Buscar producto</label>
                                    <div class="pos-search-controls">
                                        <div class="pos-autocomplete">
                                            <input wire:model.live.debounce.250ms="productSearch" wire:keydown.enter.prevent="addItemBySearch" type="text" id="product_search" class="form-control" autocomplete="off" aria-controls="product-suggestions" placeholder="Nombre, código o marca">
                                            @if (trim($productSearch) !== '')
                                                <div id="product-suggestions" class="pos-suggestions" role="listbox" aria-label="Productos encontrados">
                                                    @forelse ($this->productSuggestions() as $suggestion)
                                                        <div class="pos-suggestion" wire:key="suggestion-{{ $suggestion['id'] }}">
                                                            <button type="button" role="option" aria-selected="false" wire:click="selectProduct({{ $suggestion['id'] }})" @disabled($suggestion['stock'] <= 0)>
                                                                <strong>{{ $suggestion['name'] }}</strong>
                                                                <span>{{ $suggestion['sku'] }} · {{ $suggestion['brand'] ?: 'Sin marca' }}</span>
                                                            </button>
                                                            <span @class(['pos-stock-pill', 'is-empty' => $suggestion['stock'] <= 0])>{{ $suggestion['tracks_inventory'] ? ($suggestion['stock'] > 0 ? 'Stock '.$suggestion['stock'] : 'Sin stock') : 'Servicio' }}</span>
                                                        </div>
                                                    @empty
                                                        <p class="pos-suggestions-empty">No hay coincidencias. Puedes buscar con más filtros o crear el producto.</p>
                                                    @endforelse
                                                </div>
                                            @endif
                                        </div>
                                        <button type="button" class="btn btn-outline-primary" wire:click="openAdvancedSearch">Búsqueda avanzada</button>
                                        @if ($canCreateProduct)
                                            <button type="button" class="btn btn-primary" wire:click="openQuickProduct">Crear producto</button>
                                        @endif
                                    </div>
                                    @error('productSearch')
                                        <small class="text-danger d-block mt-2">{{ $message }}</small>
                                    @enderror
                                    @if ($productFeedback !== '')
                                        <p class="small text-success mb-0 mt-2" role="status">{{ $productFeedback }}</p>
                                    @endif
                                </div>

                                <div class="table-responsive pos-items-container">
                                    <p class="small text-muted mb-2">Escribe los decimales con punto. Por ahora, los servicios admiten hasta 3 decimales en cantidad, los productos con inventario requieren unidades enteras y el precio admite hasta 2 decimales.</p>
                                    <table class="table table-hover align-middle mb-0 pos-items-table">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Stock</th>
                                                <th>Cantidad</th>
                                                <th>Precio unit.</th>
                                                <th>Subtotal</th>
                                                <th><span class="sr-only">Acciones</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($items as $index => $item)
                                                @php
                                                    $product = collect($productIndex)->firstWhere('id', (int) ($item['product_id'] ?: 0));
                                                    $lineSubtotal = ((float) $item['quantity']) * ((float) $item['unit_price']);
                                                @endphp
                                                <tr wire:key="pos-item-{{ $index }}">
                                                    <td data-label="Producto">
                                                        <select
                                                            wire:model.live="items.{{ $index }}.product_id"
                                                            name="items[{{ $index }}][product_id]"
                                                            class="form-control product-select"
                                                            aria-label="Producto del ítem {{ $index + 1 }}"
                                                        >
                                                            <option value="">Seleccionar...</option>
                                                            @foreach ($productIndex as $option)
                                                                @if ($option['stock'] > 0 || (string) $option['id'] === (string) $item['product_id'])
                                                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td data-label="Stock">{{ $product['stock'] ?? 0 }}</td>
                                                    <td data-label="Cantidad">
                                                        <input
                                                            wire:model.blur="items.{{ $index }}.quantity"
                                                            type="text"
                                                            inputmode="decimal"
                                                            autocomplete="off"
                                                            name="items[{{ $index }}][quantity]"
                                                            class="form-control pos-decimal-input"
                                                            aria-label="Cantidad del ítem {{ $index + 1 }}"
                                                        >
                                                    </td>
                                                    <td data-label="Precio unit.">
                                                        <input
                                                            wire:model.blur="items.{{ $index }}.unit_price"
                                                            type="text"
                                                            inputmode="decimal"
                                                            autocomplete="off"
                                                            placeholder="0.00"
                                                            name="items[{{ $index }}][unit_price]"
                                                            class="form-control pos-decimal-input"
                                                            aria-label="Precio unitario del ítem {{ $index + 1 }}"
                                                        >
                                                    </td>
                                                    <td data-label="Subtotal">{{ number_format($lineSubtotal, 2) }}</td>
                                                    <td class="text-end pos-item-actions">
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

                        <div class="col-lg-4 pos-workspace__summary">
                            <div class="summary-card">
                                <p class="summary-kicker">Resumen parcial</p>
                                <div class="summary-line">
                                    <span>Items</span>
                                    <strong>{{ rtrim(rtrim(number_format($this->itemCount(), 3, '.', ','), '0'), '.') }}</strong>
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
                    <div class="row g-4 pos-workspace" wire:key="pos-step-customer" @if ($currentStep !== 1) hidden @endif>
                        <div class="col-lg-8 pos-workspace__primary">
                            <div class="wizard-card">
                                <div class="wizard-card-header">
                                    <div>
                                        <h3>2. Informacion del cliente</h3>
                                        <p>Completa solo lo necesario para el tipo de comprobante seleccionado.</p>
                                    </div>
                                </div>

                                <div class="form-row pos-field-grid pos-field-grid--customer">
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
                                        <div class="input-group pos-lookup-controls">
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

                                <div class="form-row pos-field-grid pos-field-grid--address">
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

                        <div class="col-lg-4 pos-workspace__summary">
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
                    <div class="row g-4 pos-workspace" wire:key="pos-step-payment" @if ($currentStep !== 2) hidden @endif>
                        <div class="col-lg-7 pos-workspace__primary">
                            <div class="wizard-card">
                                <div class="wizard-card-header">
                                    <div>
                                        <h3>3. Pago y cierre</h3>
                                        <p>Ajusta condiciones finales y confirma el registro.</p>
                                    </div>
                                </div>

                                <div class="form-row pos-field-grid pos-field-grid--payment">
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
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row pos-field-grid pos-field-grid--adjustments">
                                    <div class="form-group col-md-4">
                                        <label for="tax_rate">IGV (tasa: 0.18 = 18%)</label>
                                        <input wire:model.blur="taxRate" type="text" inputmode="decimal" autocomplete="off" class="form-control pos-decimal-input" id="tax_rate" name="tax_rate">
                                        @error('taxRate') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="discount">Descuento</label>
                                        <input wire:model.blur="discount" type="text" inputmode="decimal" autocomplete="off" class="form-control pos-decimal-input" id="discount" name="discount">
                                        @error('discount') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="shipping">Envio</label>
                                        <input wire:model.blur="shipping" type="text" inputmode="decimal" autocomplete="off" class="form-control pos-decimal-input" id="shipping" name="shipping">
                                        @error('shipping') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="observations">Observaciones</label>
                                    <textarea wire:model.live="observations" class="form-control" id="observations" name="observations" rows="3" placeholder="Notas rapidas para la venta"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5 pos-workspace__summary">
                            <div class="summary-card summary-card-strong">
                                <p class="summary-kicker">Cierre de venta</p>
                                <div class="summary-line">
                                    <span>Documento</span>
                                    <strong>{{ $meta['title'] }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Sucursal</span>
                                    <strong>{{ $saleBranches->firstWhere('id', (int) $branchId)?->name ?? 'Sin seleccionar' }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Almacén</span>
                                    <strong>{{ $saleWarehouses->firstWhere('id', (int) $warehouseId)?->name ?? 'Sin seleccionar' }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Cliente</span>
                                    <strong>{{ $customer['name'] ?: 'Sin definir' }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Items</span>
                                    <strong>{{ rtrim(rtrim(number_format($this->itemCount(), 3, '.', ','), '0'), '.') }}</strong>
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
                                    <strong>{{ number_format((float) $discount, 2) }}</strong>
                                </div>
                                <div class="summary-line">
                                    <span>Envio</span>
                                    <strong>{{ number_format((float) $shipping, 2) }}</strong>
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

    @include('livewire.admin.partials.pos-advanced-search')
    @include('livewire.admin.partials.pos-quick-product')
</div>

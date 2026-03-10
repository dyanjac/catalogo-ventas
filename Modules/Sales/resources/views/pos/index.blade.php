@extends('layouts.admin')

@section('title', 'Punto de venta')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header title="Punto de venta (POS)">
            <x-slot:actions>
                <a href="{{ route('admin.billing.documents.index') }}" class="btn btn-light border rounded-pill px-4">Ver docs electrónicos</a>
            </x-slot:actions>
        </x-admin.page-header>

        <form method="POST" action="{{ route('admin.sales.pos.store') }}" class="card border border-secondary rounded-3">
            @csrf
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo operación</label>
                        <select name="document_type" class="form-select" required>
                            <option value="order" @selected(old('document_type') === 'order')>Pedido</option>
                            <option value="boleta" @selected(old('document_type') === 'boleta')>Boleta</option>
                            <option value="factura" @selected(old('document_type') === 'factura')>Factura</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select" required>
                            <option value="PEN" @selected(old('currency', $defaultCurrency) === 'PEN')>PEN</option>
                            <option value="USD" @selected(old('currency', $defaultCurrency) === 'USD')>USD</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Método pago</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Efectivo</option>
                            <option value="transfer" @selected(old('payment_method') === 'transfer')>Transferencia</option>
                            <option value="card" @selected(old('payment_method') === 'card')>Tarjeta</option>
                            <option value="yape" @selected(old('payment_method') === 'yape')>Yape</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado pago</label>
                        <select name="payment_status" class="form-select" required>
                            <option value="pending" @selected(old('payment_status', 'pending') === 'pending')>Pendiente</option>
                            <option value="paid" @selected(old('payment_status') === 'paid')>Pagado</option>
                            <option value="failed" @selected(old('payment_status') === 'failed')>Fallido</option>
                            <option value="refunded" @selected(old('payment_status') === 'refunded')>Reembolsado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">IGV (%)</label>
                        <input type="number" step="0.0001" min="0" max="1" name="tax_rate" class="form-control" value="{{ old('tax_rate', $defaultTaxRate) }}">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Cliente</label>
                        <input type="text" name="customer[name]" class="form-control" value="{{ old('customer.name') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="customer[address]" class="form-control" value="{{ old('customer.address') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ciudad</label>
                        <input type="text" name="customer[city]" class="form-control" value="{{ old('customer.city') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="customer[phone]" class="form-control" value="{{ old('customer.phone') }}" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Doc. tipo</label>
                        <select name="customer[document_type]" class="form-select">
                            <option value="">-</option>
                            <option value="DNI" @selected(old('customer.document_type') === 'DNI')>DNI</option>
                            <option value="RUC" @selected(old('customer.document_type') === 'RUC')>RUC</option>
                            <option value="CE" @selected(old('customer.document_type') === 'CE')>CE</option>
                            <option value="PAS" @selected(old('customer.document_type') === 'PAS')>PAS</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Doc. nro</label>
                        <input type="text" name="customer[document_number]" class="form-control" value="{{ old('customer.document_number') }}">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-2">
                        <label class="form-label">Descuento</label>
                        <input type="number" step="0.01" min="0" name="discount" class="form-control" value="{{ old('discount', 0) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Envío</label>
                        <input type="number" step="0.01" min="0" name="shipping" class="form-control" value="{{ old('shipping', 0) }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Observaciones</label>
                        <input type="text" name="observations" class="form-control" value="{{ old('observations') }}">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Ítems de venta</h5>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" id="add-item">Agregar ítem</button>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle" id="items-table">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Precio unit.</th>
                                <th class="text-end">Subtotal</th>
                                <th></th>
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
                                <td><input type="number" min="1" name="items[0][quantity]" class="form-control text-end qty-input" value="1" required></td>
                                <td><input type="number" min="0" step="0.01" name="items[0][unit_price]" class="form-control text-end price-input" value="0" required></td>
                                <td class="text-end subtotal-cell">0.00</td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-item">Quitar</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary rounded-pill px-4">Registrar venta</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.querySelector('#items-table tbody');
    const addBtn = document.getElementById('add-item');
    let index = 1;

    function updateRow(row) {
        const select = row.querySelector('.product-select');
        const stockCell = row.querySelector('.stock-cell');
        const qtyInput = row.querySelector('.qty-input');
        const priceInput = row.querySelector('.price-input');
        const subtotalCell = row.querySelector('.subtotal-cell');
        const selected = select.options[select.selectedIndex];
        const stock = Number(selected?.dataset?.stock || 0);
        const defaultPrice = Number(selected?.dataset?.price || 0);
        const qty = Number(qtyInput.value || 0);
        const price = Number(priceInput.value || 0);

        stockCell.textContent = String(stock);
        if (!price || price <= 0) {
            priceInput.value = defaultPrice.toFixed(2);
        }

        const subtotal = Number(qtyInput.value || 0) * Number(priceInput.value || 0);
        subtotalCell.textContent = subtotal.toFixed(2);
    }

    function bindRow(row) {
        row.querySelector('.product-select').addEventListener('change', () => updateRow(row));
        row.querySelector('.qty-input').addEventListener('input', () => updateRow(row));
        row.querySelector('.price-input').addEventListener('input', () => updateRow(row));
        row.querySelector('.remove-item').addEventListener('click', () => {
            if (tableBody.querySelectorAll('.item-row').length > 1) {
                row.remove();
            }
        });
    }

    bindRow(tableBody.querySelector('.item-row'));

    addBtn.addEventListener('click', () => {
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
    });
});
</script>
@endpush

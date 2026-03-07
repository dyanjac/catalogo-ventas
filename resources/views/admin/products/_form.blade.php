<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label">Nombre</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">SKU</label>
        <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}" placeholder="Auto">
    </div>
    <div class="col-md-3">
        <label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $product->slug) }}" placeholder="Auto">
    </div>

    <div class="col-md-4">
        <label class="form-label">Categoría</label>
        <select name="category_id" class="form-select" required>
            <option value="">Seleccionar</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Unidad de medida</label>
        <select name="unit_measure_id" class="form-select" required>
            <option value="">Seleccionar</option>
            @foreach($unitMeasures as $unit)
                <option value="{{ $unit->id }}" @selected((int) old('unit_measure_id', $product->unit_measure_id) === $unit->id)>
                    {{ $unit->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Tipo afectación</label>
        <select name="tax_affectation" class="form-select" required>
            @foreach($taxAffectations as $tax)
                <option value="{{ $tax }}" @selected(old('tax_affectation', $product->tax_affectation) === $tax)>{{ $tax }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Precio compra</label>
        <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" value="{{ old('purchase_price', $product->purchase_price) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Precio venta</label>
        <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="{{ old('sale_price', $product->sale_price) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Precio mayor</label>
        <input type="number" step="0.01" min="0" name="wholesale_price" class="form-control" value="{{ old('wholesale_price', $product->wholesale_price) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Precio promedio</label>
        <input type="number" step="0.01" min="0" name="average_price" class="form-control" value="{{ old('average_price', $product->average_price) }}">
    </div>

    <div class="col-md-3">
        <label class="form-label">Stock</label>
        <input type="number" min="0" name="stock" class="form-control" value="{{ old('stock', $product->stock ?? 0) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Stock mínimo</label>
        <input type="number" min="0" name="min_stock" class="form-control" value="{{ old('min_stock', $product->min_stock ?? 0) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Cuenta contable</label>
        <input type="text" name="account" class="form-control" value="{{ old('account', $product->account) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Cuenta ingresos</label>
        <input type="text" name="account_revenue" class="form-control" value="{{ old('account_revenue', $product->account_revenue) }}" placeholder="70XXXX">
    </div>
    <div class="col-md-3">
        <label class="form-label">Cuenta por cobrar</label>
        <input type="text" name="account_receivable" class="form-control" value="{{ old('account_receivable', $product->account_receivable) }}" placeholder="12XXXX">
    </div>
    <div class="col-md-3">
        <label class="form-label">Cuenta inventario</label>
        <input type="text" name="account_inventory" class="form-control" value="{{ old('account_inventory', $product->account_inventory) }}" placeholder="20XXXX">
    </div>
    <div class="col-md-3">
        <label class="form-label">Cuenta costo venta</label>
        <input type="text" name="account_cogs" class="form-control" value="{{ old('account_cogs', $product->account_cogs) }}" placeholder="69XXXX">
    </div>
    <div class="col-md-3">
        <label class="form-label">Cuenta impuesto</label>
        <input type="text" name="account_tax" class="form-control" value="{{ old('account_tax', $product->account_tax) }}" placeholder="40XXXX">
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check me-4">
            <input type="hidden" name="uses_series" value="0">
            <input class="form-check-input" type="checkbox" name="uses_series" id="uses_series" value="1" @checked(old('uses_series', $product->uses_series))>
            <label class="form-check-label" for="uses_series">Usa serie</label>
        </div>
        <div class="form-check me-4">
            <input type="hidden" name="requires_accounting_entry" value="0">
            <input class="form-check-input" type="checkbox" name="requires_accounting_entry" id="requires_accounting_entry" value="1" @checked(old('requires_accounting_entry', $product->requires_accounting_entry ?? true))>
            <label class="form-check-label" for="requires_accounting_entry">Genera asiento contable</label>
        </div>
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))>
            <label class="form-check-label" for="is_active">Activo</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Descripción</label>
        <textarea name="description" rows="4" class="form-control">{{ old('description', $product->description) }}</textarea>
    </div>

</div>


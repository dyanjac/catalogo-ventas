<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nombre</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $category->slug) }}" placeholder="Se genera automaticamente si se deja vacio">
    </div>
    <div class="col-12">
        <label class="form-label">Descripcion</label>
        <textarea name="description" rows="4" class="form-control" placeholder="Describe la familia de productos">{{ old('description', $category->description) }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label">Tratamiento contable</label>
        <select name="accounting_treatment" class="form-select" required>
            @foreach($accountingTreatments as $treatment)
                <option value="{{ $treatment->value }}" @selected(old('accounting_treatment', $category->accounting_treatment?->value ?? \Modules\Catalog\Enums\ProductAccountingTreatment::Inherit->value) === $treatment->value)>
                    {{ $treatment->label() }}
                </option>
            @endforeach
        </select>
    </div>
    @foreach([
        'account_revenue' => 'Cuenta de ingresos',
        'account_deferred_revenue' => 'Cuenta de ingresos diferidos',
        'account_receivable' => 'Cuenta por cobrar',
        'account_inventory' => 'Cuenta de inventario',
        'account_cogs' => 'Cuenta de costo de venta',
        'account_tax' => 'Cuenta de impuesto',
    ] as $field => $label)
        <div class="col-md-4">
            <label class="form-label">{{ $label }}</label>
            <input type="text" name="{{ $field }}" maxlength="120" class="form-control" value="{{ old($field, $category->{$field}) }}">
        </div>
    @endforeach
</div>

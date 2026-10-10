<div class="pos-modal" wire:keydown.escape="closeQuickProduct" @if (! $quickProductOpen) hidden @endif role="dialog" aria-modal="true" aria-label="Creación rápida de producto">
    <div class="pos-modal-panel">
        <div class="pos-modal-header">
            <div>
                <p class="pos-kicker mb-1">Producto · Paso {{ $quickProductStep }} de 2</p>
                <h2>{{ $quickProductStep === 1 ? 'Crear producto' : 'Cargar stock' }}</h2>
            </div>
            <button type="button" class="btn btn-outline-secondary" wire:click="closeQuickProduct" aria-label="Cerrar creación rápida">Cerrar</button>
        </div>
        <div @if ($quickProductStep !== 1) hidden @endif>
            <p class="text-muted">Datos esenciales para venderlo. El resto se puede completar luego en el catálogo.</p>
            <div class="pos-quick-grid">
                <div class="pos-field-wide"><label for="quick_name">Nombre *</label><input id="quick_name" class="form-control" type="text" wire:model.blur="quickProduct.name" maxlength="190">@error('quickProduct.name')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div><label for="quick_sku">Código / SKU</label><input id="quick_sku" class="form-control" type="text" wire:model.blur="quickProduct.sku" placeholder="Automático">@error('quickProduct.sku')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div><label for="quick_brand">Marca</label><input id="quick_brand" class="form-control" type="text" wire:model.blur="quickProduct.brand"></div>
                <div><label for="quick_category">Categoría *</label><select id="quick_category" class="form-control" wire:model.live="quickProduct.category_id"><option value="">Seleccionar</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>@error('quickProduct.category_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div><label for="quick_unit">Unidad *</label><select id="quick_unit" class="form-control" wire:model.live="quickProduct.unit_measure_id"><option value="">Seleccionar</option>@foreach ($unitMeasures as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select>@error('quickProduct.unit_measure_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div><label for="quick_type">Tipo *</label><select id="quick_type" class="form-control" wire:model.live="quickProduct.product_type"><option value="bien_fisico">Bien físico</option><option value="servicio">Servicio</option></select></div>
                <div><label for="quick_sale_price">Precio de venta *</label><input id="quick_sale_price" class="form-control" type="text" inputmode="decimal" wire:model.blur="quickProduct.sale_price" placeholder="0.00">@error('quickProduct.sale_price')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div><label for="quick_purchase_price">Costo de compra</label><input id="quick_purchase_price" class="form-control" type="text" inputmode="decimal" wire:model.blur="quickProduct.purchase_price" placeholder="0.00">@error('quickProduct.purchase_price')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="pos-field-wide"><label for="quick_description">Descripción</label><textarea id="quick_description" class="form-control" rows="2" wire:model.blur="quickProduct.description"></textarea></div>
            </div>
            <div class="pos-modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="closeQuickProduct">Cancelar</button><button type="button" class="btn btn-primary" wire:click="saveQuickProduct" wire:loading.attr="disabled">Crear y continuar</button></div>
        </div>
        <div @if ($quickProductStep !== 2) hidden @endif>
            <p class="text-muted"><strong>{{ $quickProduct['name'] }}</strong> ya está en el catálogo. El ingreso de stock es opcional; la venta en curso permanece intacta.</p>
            @if ($canAddStock && $warehouses->isNotEmpty())
                <div class="pos-quick-grid">
                    <div class="pos-field-wide"><label for="quick_warehouse">Almacén *</label><select id="quick_warehouse" class="form-control" wire:model.live="quickStockWarehouseId"><option value="">Seleccionar</option>@foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select>@error('quickStockWarehouseId')<small class="text-danger">{{ $message }}</small>@enderror</div>
                    <div><label for="quick_stock_quantity">Cantidad a ingresar *</label><input id="quick_stock_quantity" class="form-control" type="text" inputmode="numeric" wire:model.blur="quickStockQuantity" placeholder="1">@error('quickStockQuantity')<small class="text-danger">{{ $message }}</small>@enderror</div>
                    <div><label for="quick_stock_cost">Costo unitario *</label><input id="quick_stock_cost" class="form-control" type="text" inputmode="decimal" wire:model.blur="quickStockUnitCost" placeholder="0.00">@error('quickStockUnitCost')<small class="text-danger">{{ $message }}</small>@enderror</div>
                </div>
                @error('quickStock')<p class="text-danger mt-2">{{ $message }}</p>@enderror
                <div class="pos-modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="skipQuickStock">Ahora no</button><button type="button" class="btn btn-primary" wire:click="saveQuickStock" wire:loading.attr="disabled">Ingresar stock y agregar</button></div>
            @else
                <p class="alert alert-info">Para ingresar stock necesitas permiso para crear y confirmar documentos de inventario y un almacén activo en tu sucursal.</p>
                <div class="pos-modal-footer"><button type="button" class="btn btn-primary" wire:click="skipQuickStock">Continuar la venta</button></div>
            @endif
        </div>
    </div>
</div>

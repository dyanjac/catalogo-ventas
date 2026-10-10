<div class="pos-modal" wire:keydown.escape="closeAdvancedSearch" @if (! $advancedSearchOpen) hidden @endif role="dialog" aria-modal="true" aria-label="Búsqueda avanzada de productos">
    <div class="pos-modal-panel pos-modal-panel--wide">
        <div class="pos-modal-header">
            <div>
                <p class="pos-kicker mb-1">Catálogo</p>
                <h2>Búsqueda avanzada</h2>
            </div>
            <button type="button" class="btn btn-outline-secondary" wire:click="closeAdvancedSearch" aria-label="Cerrar búsqueda avanzada">Cerrar</button>
        </div>
        <div class="pos-filter-grid">
            <div><label for="advanced_term">Nombre o término</label><input id="advanced_term" type="text" class="form-control" wire:model.live.debounce.250ms="advancedFilters.term"></div>
            <div><label for="advanced_category">Categoría</label><select id="advanced_category" class="form-control" wire:model.live="advancedFilters.category_id"><option value="">Todas</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
            <div><label for="advanced_stock">Stock</label><select id="advanced_stock" class="form-control" wire:model.live="advancedFilters.stock"><option value="available">Disponibles</option><option value="out">Sin stock</option><option value="all">Todos</option></select></div>
            <div><label for="advanced_min_price">Precio desde</label><input id="advanced_min_price" type="text" inputmode="decimal" class="form-control" wire:model.live.debounce.250ms="advancedFilters.min_price" placeholder="0.00"></div>
            <div><label for="advanced_max_price">Precio hasta</label><input id="advanced_max_price" type="text" inputmode="decimal" class="form-control" wire:model.live.debounce.250ms="advancedFilters.max_price" placeholder="0.00"></div>
            <div><label for="advanced_brand">Marca</label><input id="advanced_brand" type="text" class="form-control" wire:model.live.debounce.250ms="advancedFilters.brand"></div>
            <div><label for="advanced_sku">Código / SKU</label><input id="advanced_sku" type="text" class="form-control" wire:model.live.debounce.250ms="advancedFilters.sku"></div>
            <div><label for="advanced_description">Descripción</label><input id="advanced_description" type="text" class="form-control" wire:model.live.debounce.250ms="advancedFilters.description"></div>
        </div>
        <p class="small text-muted mt-3 mb-2">Se muestran hasta 60 coincidencias del catálogo de tu alcance.</p>
        <div class="pos-result-list">
            @if ($advancedSearchOpen)
            @forelse ($this->advancedResults() as $result)
                <div class="pos-result" wire:key="advanced-product-{{ $result['id'] }}">
                    <div>
                        <strong>{{ $result['name'] }}</strong>
                        <span>{{ $result['sku'] }} · {{ $result['brand'] ?: 'Sin marca' }} · S/ {{ \App\Support\Decimal::unitPriceForInput($result['price']) }}</span>
                        @if (($result['description'] ?? '') !== '') <small>{{ mb_strimwidth($result['description'], 0, 90, '…') }}</small> @endif
                    </div>
                    <div class="pos-result-actions">
                        <span @class(['pos-stock-pill', 'is-empty' => $result['stock'] <= 0])>{{ $result['tracks_inventory'] ? 'Stock '.$result['stock'] : 'Servicio' }}</span>
                        @if ($result['stock'] > 0)
                            <button type="button" class="btn btn-primary btn-sm" wire:click="selectProduct({{ $result['id'] }})">Agregar</button>
                        @elseif ($canAddStock && $result['tracks_inventory'])
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="openStockForProduct({{ $result['id'] }})">Cargar stock</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="pos-empty-results">
                    <p>No encontramos productos con estos filtros.</p>
                    @if ($canCreateProduct)<button type="button" class="btn btn-primary" wire:click="openQuickProduct">Crear producto</button>@endif
                </div>
            @endforelse
            @endif
        </div>
    </div>
</div>

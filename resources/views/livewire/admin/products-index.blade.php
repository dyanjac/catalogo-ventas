<div class="space-y-6">
    <x-admin.page-header
        title="Administrar productos"
        description="Controla catálogo, stock, precios y visibilidad comercial desde una sola vista."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.products.create') }}" variant="primary" icon="plus">
                Nuevo producto
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card border-0">
        <div class="card-body">
            <div class="grid gap-4 lg:grid-cols-[1.4fr,1fr,1fr,0.7fr]">
                <div>
                    <label class="form-label">Buscar (nombre o SKU)</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Producto o SKU" />
                </div>
                <div>
                    <label class="form-label">Categoria</label>
                    <select wire:model.live="categoryId" class="form-select">
                        <option value="">Todas</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Unidad</label>
                    <select wire:model.live="unitMeasureId" class="form-select">
                        <option value="">Todas</option>
                        @foreach($unitMeasures as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Activo</label>
                    <select wire:model.live="isActive" class="form-select">
                        <option value="">Todos</option>
                        <option value="1">Si</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button wire:click="clearFilters" variant="outline" icon="arrow-path">
                    Limpiar filtros
                </flux:button>
                <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-500">
                    {{ $products->total() }} productos encontrados
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 overflow-hidden">
        <div class="card-body p-0">
            <div wire:loading.flex class="align-items-center justify-content-center border-bottom px-4 py-3 text-sm text-muted">
                Actualizando catalogo...
            </div>

            <div class="overflow-x-auto">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Producto</th>
                            <th>Categoria</th>
                            <th>Unidad</th>
                            <th>Venta</th>
                            <th>Mayor</th>
                            <th>Stock</th>
                            <th>Minimo</th>
                            <th>Activo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr wire:key="product-{{ $product->id }}">
                                <td class="fw-semibold">{{ $product->sku ?? '-' }}</td>
                                <td>
                                    <div class="fw-semibold text-slate-800">{{ $product->name }}</div>
                                    <small class="text-muted">{{ $product->tax_affectation }}</small>
                                </td>
                                <td>{{ $product->category?->name ?? '-' }}</td>
                                <td>{{ $product->unitMeasure?->name ?? '-' }}</td>
                                <td>S/ {{ number_format((float) ($product->sale_price ?? 0), 2) }}</td>
                                <td>S/ {{ number_format((float) ($product->wholesale_price ?? 0), 2) }}</td>
                                <td>
                                    {{ $product->stock }}
                                    @if($product->stock <= $product->min_stock)
                                        <span class="badge bg-danger ms-1">Bajo</span>
                                    @endif
                                </td>
                                <td>{{ $product->min_stock }}</td>
                                <td>
                                    <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $product->is_active ? 'Si' : 'No' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <flux:button href="{{ route('admin.products.show', $product) }}" variant="outline" size="sm">
                                            Ver
                                        </flux:button>
                                        <flux:button href="{{ route('admin.products.edit', $product) }}" variant="primary" size="sm">
                                            Editar
                                        </flux:button>
                                        <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('¿Eliminar este producto?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-5 text-center text-muted">No hay productos para mostrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        {{ $products->links() }}
    </div>
</div>

<div class="space-y-6">
    <x-admin.page-header
        title="Administrar productos"
        description="Controla catalogo, stock y cobertura operativa por sucursal y almacen desde una sola pantalla."
    >
        <x-slot:actions>
            <flux:button href="{{ route('admin.inventory.warehouses.index') }}" variant="outline" icon="building-office-2">
                Almacenes
            </flux:button>
            <flux:button href="{{ route('admin.inventory.index') }}" variant="outline" icon="archive-box">
                Ver inventario
            </flux:button>
            <flux:button href="{{ route('admin.products.create') }}" variant="primary" icon="plus">
                Nuevo producto
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    @if($flashMessage)
        <div class="alert alert-{{ $flashTone === 'success' ? 'success' : 'danger' }} mb-0">
            {{ $flashMessage }}
        </div>
    @endif

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
                @if($activeBranchId)
                    <div class="inline-flex items-center rounded-full bg-sky-50 px-3 py-2 text-sm text-sky-700">
                        Stock visible segun sucursal activa #{{ $activeBranchId }}
                    </div>
                @endif
                <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    {{ $branches->count() }} sucursal(es) operativas
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 2xl:grid-cols-[1.45fr,1fr]">
        <div class="space-y-4">
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
                                    <th>Stock sucursal</th>
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
                                        <td>
                                            {{ $product->effective_stock }}
                                            @if($product->effective_stock <= $product->effective_min_stock)
                                                <span class="badge bg-danger ms-1">Bajo</span>
                                            @endif
                                        </td>
                                        <td>{{ $product->effective_min_stock }}</td>
                                        <td>
                                            <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $product->is_active ? 'Si' : 'No' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                                @if($canManageAssignments)
                                                    <flux:button type="button" wire:click="selectProductForAssignments({{ $product->id }})" variant="{{ $selectedProduct?->id === $product->id ? 'primary' : 'outline' }}" size="sm">
                                                        Cobertura
                                                    </flux:button>
                                                @endif
                                                <flux:button href="{{ route('admin.products.show', $product) }}" variant="outline" size="sm">
                                                    Ver
                                                </flux:button>
                                                <flux:button href="{{ route('admin.products.edit', $product) }}" variant="primary" size="sm">
                                                    Editar
                                                </flux:button>
                                                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Eliminar este producto?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-5 text-center text-muted">No hay productos para mostrar.</td>
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

        <div>
            <div class="card border-0 overflow-hidden">
                <div class="card-body space-y-4">
                    <div>
                        <div class="text-uppercase text-xs tracking-[0.3em] text-primary">Cobertura operativa</div>
                        <h3 class="mb-1 text-2xl font-semibold text-slate-900">
                            {{ $selectedProduct ? $selectedProduct->name : 'Selecciona un producto' }}
                        </h3>
                        <p class="mb-0 text-muted">Habilita el producto por sucursal y por almacen para controlar stock visible, minimos y operaciones permitidas.</p>
                    </div>

                    @if($selectedProduct)
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-4 border border-slate-200 p-3">
                                <div class="text-sm text-muted">SKU</div>
                                <div class="fw-semibold text-slate-900">{{ $selectedProduct->sku ?? '-' }}</div>
                            </div>
                            <div class="rounded-4 border border-slate-200 p-3">
                                <div class="text-sm text-muted">Categoria</div>
                                <div class="fw-semibold text-slate-900">{{ $selectedProduct->category?->name ?? '-' }}</div>
                            </div>
                        </div>

                        @if($canManageAssignments)
                            <form wire:submit="saveAssignments" class="space-y-4">
                                <div class="space-y-3">
                                    @foreach($branches as $branch)
                                        <div class="rounded-4 border border-slate-200 p-3">
                                            <div class="grid gap-3 lg:grid-cols-[1fr,120px] lg:items-center">
                                                <label class="flex items-start gap-3">
                                                    <input type="checkbox" wire:model="assignmentBranchStates.{{ $branch->id }}" class="mt-1">
                                                    <span>
                                                        <span class="d-block fw-semibold text-slate-900">{{ $branch->name }}</span>
                                                        <span class="text-sm text-muted">{{ $branch->code }} · habilita el producto para la sucursal.</span>
                                                    </span>
                                                </label>
                                                <div>
                                                    <label class="form-label">Minimo sucursal</label>
                                                    <input type="number" min="0" wire:model="assignmentBranchMinStocks.{{ $branch->id }}" class="form-control">
                                                </div>
                                            </div>

                                            @if($hasWarehouseSchema)
                                                <div class="mt-3 space-y-2 rounded-4 bg-slate-50 p-3">
                                                    <div class="text-sm fw-semibold text-slate-700">Almacenes de {{ $branch->name }}</div>
                                                    @forelse(($warehousesByBranch[$branch->id] ?? collect()) as $warehouse)
                                                        <div class="grid gap-3 md:grid-cols-[1fr,120px] md:items-center rounded-4 border border-slate-200 bg-white p-3" wire:key="product-assignment-warehouse-{{ $warehouse->id }}">
                                                            <label class="flex items-start gap-3">
                                                                <input type="checkbox" wire:model="assignmentWarehouseStates.{{ $warehouse->id }}" class="mt-1">
                                                                <span>
                                                                    <span class="d-block fw-semibold text-slate-900">{{ $warehouse->name }}</span>
                                                                    <span class="text-sm text-muted">{{ $warehouse->code }} · stock y kardex operan desde este almacen.</span>
                                                                </span>
                                                            </label>
                                                            <div>
                                                                <label class="form-label">Minimo almacen</label>
                                                                <input type="number" min="0" wire:model="assignmentWarehouseMinStocks.{{ $warehouse->id }}" class="form-control">
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <div class="rounded-4 border border-dashed p-3 text-sm text-muted">
                                                            Esta sucursal aun no tiene almacenes activos.
                                                        </div>
                                                    @endforelse
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="d-flex justify-content-end">
                                    <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                                        Guardar cobertura
                                    </flux:button>
                                </div>
                            </form>
                        @else
                            <div class="rounded-4 border border-dashed p-4 text-sm text-muted">
                                Tu rol puede consultar productos, pero no modificar su cobertura por sucursal y almacen.
                            </div>
                        @endif
                    @else
                        <div class="rounded-4 border border-dashed p-4 text-sm text-muted">
                            Selecciona un producto del listado para administrar su cobertura operativa.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>


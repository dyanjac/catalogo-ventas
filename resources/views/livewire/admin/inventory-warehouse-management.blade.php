<div class="space-y-6">
    <x-admin.page-header
        title="Almacenes"
        description="Administra los almacenes operativos y vincula cada uno a una sucursal para mantener stock, costo promedio y trazabilidad por ubicacion."
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                @if($canManageBranches)
                    <flux:button href="{{ route('admin.security.branches.index') }}" variant="outline" icon="building-storefront">
                        Sucursales
                    </flux:button>
                @endif
                <flux:button href="{{ route('admin.products.index') }}" variant="outline" icon="cube">
                    Productos
                </flux:button>
                @if($canManageWarehouses)
                    <flux:button type="button" wire:click="createWarehouse" variant="primary" icon="plus">
                        Nuevo almacen
                    </flux:button>
                @endif
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    @if($flashMessage)
        <div class="alert alert-{{ $flashTone === 'success' ? 'success' : 'danger' }} mb-0">
            {{ $flashMessage }}
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[1.05fr,1fr]">
        <div class="space-y-4">
            <div class="card border-0">
                <div class="card-body">
                    <label class="form-label">Buscar almacen</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Codigo, nombre o sucursal" />
                </div>
            </div>

            <div class="card border-0 overflow-hidden">
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Almacen</th>
                                    <th>Sucursal</th>
                                    <th>Stock</th>
                                    <th>Estado</th>
                                    <th class="text-end">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($warehouses as $warehouse)
                                    <tr wire:key="warehouse-{{ $warehouse->id }}">
                                        <td>
                                            <div class="fw-semibold text-slate-900">{{ $warehouse->name }}</div>
                                            <small class="text-muted">{{ $warehouse->code }}</small>
                                            @if($warehouse->is_default)
                                                <span class="badge bg-info ms-2">Default</span>
                                            @endif
                                            <div class="mt-1 text-xs text-muted">{{ $warehouse->stocks_count }} stock(s) | {{ $warehouse->documents_count }} guia(s)</div>
                                        </td>
                                        <td>{{ $warehouse->branch?->name ?? 'Sin sucursal' }}</td>
                                        <td>
                                            <div class="fw-semibold text-slate-900">{{ (int) ($warehouse->stock_total ?? 0) }}</div>
                                            <small class="text-muted">Min {{ (int) ($warehouse->min_stock_total ?? 0) }}</small>
                                        </td>
                                        <td>
                                            <span class="badge {{ $warehouse->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $warehouse->is_active ? 'Activo' : 'Inactivo' }}</span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                                <flux:button href="{{ route('admin.inventory.index', ['branch_id' => $warehouse->branch_id, 'warehouse_id' => $warehouse->id]) }}" variant="outline" size="sm">
                                                    Ver stock
                                                </flux:button>
                                                <flux:button type="button" wire:click="selectWarehouse({{ $warehouse->id }})" variant="{{ $selectedWarehouseId === $warehouse->id ? 'primary' : 'outline' }}" size="sm">
                                                    Gestionar
                                                </flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-5 text-center text-muted">No hay almacenes registrados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                {{ $warehouses->links() }}
            </div>
        </div>

        <div>
            <form wire:submit="save" class="space-y-4">
                <div class="card border-0">
                    <div class="card-body space-y-4">
                        <div>
                            <div class="text-uppercase text-xs tracking-[0.3em] text-primary">Configuracion de inventario</div>
                            <h3 class="mb-1 text-2xl font-semibold text-slate-900">{{ $selectedWarehouseId ? 'Editar almacen' : 'Nuevo almacen' }}</h3>
                            <p class="mb-0 text-muted">Cada almacen pertenece a una sucursal y concentra su propio stock, costo promedio y documentos.</p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="form-label">Sucursal</label>
                                <select wire:model="branch_id" class="form-select" @disabled(! $canManageWarehouses || in_array($scopeLevel, ['branch', 'own'], true))>
                                    <option value="">Selecciona</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }} ({{ $branch->code }})</option>
                                    @endforeach
                                </select>
                                @error('branch_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div>
                                <label class="form-label">Codigo</label>
                                <input type="text" wire:model="code" class="form-control" placeholder="MAIN-01" @disabled(! $canManageWarehouses) />
                                @error('code') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div>
                                <label class="form-label">Nombre</label>
                                <input type="text" wire:model="name" class="form-control" placeholder="Almacen principal" @disabled(! $canManageWarehouses) />
                                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div>
                                <label class="form-label">Descripcion</label>
                                <input type="text" wire:model="description" class="form-control" placeholder="Recepcion, picking o despacho" @disabled(! $canManageWarehouses) />
                                @error('description') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="rounded-4 border border-slate-200 p-3 flex items-start gap-3">
                                <input type="checkbox" wire:model="is_active" class="mt-1" @disabled(! $canManageWarehouses)>
                                <span>
                                    <span class="d-block fw-semibold text-slate-900">Almacen activo</span>
                                    <span class="text-sm text-muted">Solo almacenes activos deben participar en movimientos y guias nuevas.</span>
                                </span>
                            </label>
                            <label class="rounded-4 border border-slate-200 p-3 flex items-start gap-3">
                                <input type="checkbox" wire:model="is_default" class="mt-1" @disabled(! $canManageWarehouses)>
                                <span>
                                    <span class="d-block fw-semibold text-slate-900">Almacen default de la sucursal</span>
                                    <span class="text-sm text-muted">Se usa como referencia operativa principal dentro de su sucursal.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <flux:button type="button" wire:click="createWarehouse" variant="outline" icon="arrow-path">
                        Limpiar
                    </flux:button>
                    @if($canManageWarehouses)
                        <flux:button type="submit" variant="primary" icon="archive-box" wire:loading.attr="disabled">
                            Guardar almacen
                        </flux:button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

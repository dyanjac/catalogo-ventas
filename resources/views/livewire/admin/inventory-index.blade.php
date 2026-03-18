<div class="space-y-6">
    <x-admin.page-header
        title="Inventarios por sucursal"
        description="Consulta stock operativo por producto y sucursal. Este screen ya responde al alcance branch del RBAC."
    />

    <div class="card border-0">
        <div class="card-body">
            <div class="grid gap-4 lg:grid-cols-[1.4fr,1fr]">
                <div>
                    <label class="form-label">Buscar producto o SKU</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Producto o SKU" />
                </div>
                <div>
                    <label class="form-label">Sucursal</label>
                    <select wire:model.live="branchId" class="form-select" @disabled(in_array($scopeLevel, ['branch', 'own'], true))>
                        <option value="">Todas</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button wire:click="clearFilters" variant="outline" icon="arrow-path">
                    Limpiar filtros
                </flux:button>
                <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-500">
                    {{ $stocks->total() }} registros de inventario
                </div>
                @if($effectiveBranchId)
                    <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        Vista filtrada por sucursal #{{ $effectiveBranchId }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card border-0 overflow-hidden">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Sucursal</th>
                            <th>SKU</th>
                            <th>Producto</th>
                            <th>Categoria</th>
                            <th>Stock</th>
                            <th>Minimo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stocks as $stock)
                            <tr wire:key="inventory-stock-{{ $stock->id }}">
                                <td>{{ $stock->branch?->name ?? '-' }}</td>
                                <td class="fw-semibold">{{ $stock->product?->sku ?? '-' }}</td>
                                <td>{{ $stock->product?->name ?? '-' }}</td>
                                <td>{{ $stock->product?->category?->name ?? '-' }}</td>
                                <td>
                                    {{ $stock->stock }}
                                    @if($stock->stock <= $stock->min_stock)
                                        <span class="badge bg-danger ms-1">Bajo</span>
                                    @endif
                                </td>
                                <td>{{ $stock->min_stock }}</td>
                                <td>
                                    <span class="badge {{ $stock->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $stock->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-5 text-center text-muted">No hay registros de inventario para mostrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        {{ $stocks->links() }}
    </div>
</div>

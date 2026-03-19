<div class="space-y-6">
    <x-admin.page-header
        title="Inventarios por sucursal"
        description="Consulta stock operativo, registra ajustes manuales, transferencias y revisa el kardex reciente por sucursal."
    />

    @if($flashMessage)
        <div class="alert alert-{{ $flashTone === 'success' ? 'success' : 'danger' }} mb-0">
            {{ $flashMessage }}
        </div>
    @endif

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
                <div class="inline-flex items-center rounded-full bg-sky-50 px-3 py-2 text-sm text-sky-700">
                    {{ $movements->count() }} movimientos recientes
                </div>
                <div class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-2 text-sm text-indigo-700">
                    {{ $recentTransfers->count() }} transferencias recientes
                </div>
                @if($effectiveBranchId)
                    <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        Vista filtrada por sucursal #{{ $effectiveBranchId }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-4 2xl:grid-cols-[1.3fr,1fr]">
        <div class="card border-0 overflow-hidden">
            <div class="card-body p-0">
                <div class="border-bottom px-4 py-3">
                    <h4 class="mb-1 text-lg font-semibold text-slate-900">Stock por sucursal</h4>
                    <p class="mb-0 text-sm text-muted">Vista operativa del stock actual y stock mínimo.</p>
                </div>
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
                                <th class="text-end">Accion</th>
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
                                    <td class="text-end">
                                        @if($canAdjustInventory)
                                            <flux:button type="button" wire:click="selectStock({{ $stock->id }})" variant="{{ $selectedStock?->id === $stock->id ? 'primary' : 'outline' }}" size="sm">
                                                Ajustar
                                            </flux:button>
                                        @else
                                            <span class="text-sm text-muted">Solo lectura</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-5 text-center text-muted">No hay registros de inventario para mostrar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card border-0 overflow-hidden">
                <div class="card-body">
                    <div class="mb-3">
                        <h4 class="mb-1 text-lg font-semibold text-slate-900">Transferencia entre sucursales</h4>
                        <p class="mb-0 text-sm text-muted">Genera salida en la sucursal origen y entrada en la sucursal destino dentro de la misma operación.</p>
                    </div>

                    @if($canCreateTransfer)
                        <form wire:submit="saveTransfer" class="space-y-4">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="form-label">Sucursal origen</label>
                                    <select wire:model="transferSourceBranchId" class="form-select" @disabled(in_array($scopeLevel, ['branch', 'own'], true))>
                                        <option value="">Selecciona</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('transferSourceBranchId') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                </div>
                                <div>
                                    <label class="form-label">Sucursal destino</label>
                                    <select wire:model="transferDestinationBranchId" class="form-select">
                                        <option value="">Selecciona</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('transferDestinationBranchId') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Producto</label>
                                <select wire:model="transferProductId" class="form-select">
                                    <option value="">Selecciona</option>
                                    @foreach($transferProducts as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }} · {{ $product->sku }}</option>
                                    @endforeach
                                </select>
                                @error('transferProductId') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                            </div>

                            <div class="grid gap-3 md:grid-cols-[180px,1fr]">
                                <div>
                                    <label class="form-label">Cantidad</label>
                                    <input type="number" min="1" wire:model="transferQuantity" class="form-control">
                                    @error('transferQuantity') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                </div>
                                <div>
                                    <label class="form-label">Notas</label>
                                    <input type="text" wire:model="transferNotes" class="form-control" placeholder="Observacion corta de la transferencia">
                                    @error('transferNotes') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <flux:button type="submit" variant="primary" icon="arrows-right-left" wire:loading.attr="disabled">
                                    Registrar transferencia
                                </flux:button>
                            </div>
                        </form>
                    @else
                        <div class="rounded-4 border border-dashed p-4 text-sm text-muted">
                            Tu rol puede revisar inventario, pero no registrar transferencias entre sucursales.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 overflow-hidden">
                <div class="card-body">
                    <div class="mb-3">
                        <h4 class="mb-1 text-lg font-semibold text-slate-900">Ajuste manual</h4>
                        <p class="mb-0 text-sm text-muted">Corrige stock por sucursal y genera un movimiento de tipo <code>adjustment</code>.</p>
                    </div>

                    @if($selectedStock)
                        @if($canAdjustInventory)
                            <form wire:submit="saveAdjustment" class="space-y-4">
                                <div class="rounded-4 border border-slate-200 p-3">
                                    <div class="fw-semibold text-slate-900">{{ $selectedStock->product?->name ?? 'Producto' }}</div>
                                    <div class="text-sm text-muted">{{ $selectedStock->branch?->name ?? 'Sin sucursal' }} · {{ $selectedStock->product?->sku ?? '-' }}</div>
                                    <div class="mt-2 text-sm text-muted">Stock actual: <span class="fw-semibold text-slate-900">{{ $selectedStock->stock }}</span></div>
                                </div>

                                <div>
                                    <label class="form-label">Stock objetivo</label>
                                    <input type="number" min="0" wire:model="adjustmentTargetStock" class="form-control">
                                    @error('adjustmentTargetStock') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="form-label">Motivo</label>
                                    <select wire:model="adjustmentReason" class="form-select">
                                        <option value="manual_adjustment">Ajuste manual</option>
                                        <option value="inventory_count">Conteo fisico</option>
                                        <option value="damaged_goods">Merma / danado</option>
                                        <option value="return_restock">Reposicion por devolucion</option>
                                    </select>
                                    @error('adjustmentReason') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="form-label">Notas</label>
                                    <textarea wire:model="adjustmentNotes" rows="3" class="form-control" placeholder="Detalle corto del ajuste"></textarea>
                                    @error('adjustmentNotes') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                </div>

                                <div class="d-flex justify-content-end">
                                    <flux:button type="submit" variant="primary" icon="pencil-square" wire:loading.attr="disabled">
                                        Registrar ajuste
                                    </flux:button>
                                </div>
                            </form>
                        @else
                            <div class="rounded-4 border border-dashed p-4 text-sm text-muted">
                                Tu rol puede revisar inventario, pero no registrar ajustes manuales.
                            </div>
                        @endif
                    @else
                        <div class="rounded-4 border border-dashed p-4 text-sm text-muted">
                            Selecciona un registro de stock para preparar un ajuste manual.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-0 overflow-hidden">
                <div class="card-body p-0">
                    <div class="border-bottom px-4 py-3">
                        <h4 class="mb-1 text-lg font-semibold text-slate-900">Transferencias recientes</h4>
                        <p class="mb-0 text-sm text-muted">Movimientos entre sucursales con cabecera y detalle.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse($recentTransfers as $transfer)
                            <div class="px-4 py-3" wire:key="inventory-transfer-{{ $transfer->id }}">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold text-slate-900">{{ $transfer->code }}</div>
                                        <div class="text-sm text-muted">{{ $transfer->sourceBranch?->name ?? 'Origen' }} -> {{ $transfer->destinationBranch?->name ?? 'Destino' }}</div>
                                    </div>
                                    <span class="badge bg-primary">{{ strtoupper($transfer->status) }}</span>
                                </div>
                                <div class="mt-2 text-sm text-muted">
                                    @php($line = $transfer->items->first())
                                    {{ $line?->product?->name ?? 'Sin producto' }} · Cantidad: {{ $line?->quantity ?? 0 }}
                                </div>
                                <div class="mt-2 text-sm text-muted">
                                    {{ $transfer->creator?->name ?? 'Sistema' }} · {{ $transfer->created_at?->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-5 text-center text-muted">Todavía no hay transferencias registradas.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="card border-0 overflow-hidden">
                <div class="card-body p-0">
                    <div class="border-bottom px-4 py-3">
                        <h4 class="mb-1 text-lg font-semibold text-slate-900">Kardex reciente</h4>
                        <p class="mb-0 text-sm text-muted">Historial inmediato de entradas, salidas y ajustes por sucursal.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse($movements as $movement)
                            <div class="px-4 py-3" wire:key="inventory-movement-{{ $movement->id }}">
                                <div class="d-flex justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold text-slate-900">{{ $movement->product?->name ?? 'Producto' }}</div>
                                        <div class="text-sm text-muted">{{ $movement->branch?->name ?? 'Sin sucursal' }} · {{ $movement->product?->sku ?? '-' }}</div>
                                    </div>
                                    <span class="badge {{ $movement->quantity >= 0 ? 'bg-success' : 'bg-danger' }}">
                                        {{ $movement->quantity >= 0 ? '+' : '' }}{{ $movement->quantity }}
                                    </span>
                                </div>
                                <div class="mt-2 d-flex flex-wrap gap-2 text-sm text-muted">
                                    <span>{{ strtoupper(str_replace('_', ' ', $movement->movement_type)) }}</span>
                                    <span>{{ $movement->reason ? strtoupper(str_replace('_', ' ', $movement->reason)) : 'SIN MOTIVO' }}</span>
                                    <span>{{ $movement->stock_before }} -> {{ $movement->stock_after }}</span>
                                </div>
                                <div class="mt-2 text-sm text-muted">
                                    {{ $movement->reference_code ?: 'Sin referencia' }}
                                    @if($movement->actor)
                                        · {{ $movement->actor->name }}
                                    @endif
                                    · {{ $movement->created_at?->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-5 text-center text-muted">Todavía no hay movimientos registrados.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div>
        {{ $stocks->links() }}
    </div>
</div>

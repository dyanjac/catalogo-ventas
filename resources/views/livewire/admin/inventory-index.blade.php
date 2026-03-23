<div class="space-y-6">
    <x-admin.page-header
        title="Inventarios y kardex"
        description="Opera stock por sucursal y almacen, registra guias de ingreso o salida y revisa el kardex valorizado con trazabilidad exacta."
    />

    @if($flashMessage)
        <div class="alert alert-{{ $flashTone === 'success' ? 'success' : 'danger' }} mb-0">
            {{ $flashMessage }}
        </div>
    @endif

    @unless($hasWarehouseSchema)
        <div class="alert alert-warning mb-0">
            La UI de guias por almacen esta lista, pero necesitas ejecutar las nuevas migraciones de inventario para habilitarla completamente.
        </div>
    @endunless

    <div class="card border-0">
        <div class="card-body">
            <div class="grid gap-4 xl:grid-cols-[1.2fr,220px,220px]">
                <div>
                    <label class="form-label">Buscar producto, SKU o referencia</label>
                    <flux:input wire:model.live.debounce.300ms="search" placeholder="Producto, SKU o codigo de guia" />
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
                <div>
                    <label class="form-label">Almacen</label>
                    <select wire:model.live="warehouseId" class="form-select" @disabled(! $hasWarehouseSchema || ! $canViewWarehouses)>
                        <option value="">Todos</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button wire:click="clearFilters" variant="outline" icon="arrow-path">Limpiar filtros</flux:button>
                <div class="inline-flex items-center rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-500">{{ $stocks->total() }} registros de stock</div>
                @if($canViewKardex)
                    <div class="inline-flex items-center rounded-full bg-sky-50 px-3 py-2 text-sm text-sky-700">{{ $movements->count() }} movimientos recientes</div>
                @endif
                @if($canViewDocuments)
                    <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ $recentDocuments->count() }} guias recientes</div>
                @endif
                @if($canViewTransfers)
                    <div class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-2 text-sm text-indigo-700">{{ $recentTransfers->count() }} transferencias recientes</div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-4 2xl:grid-cols-[1.4fr,1fr]">
        <div class="space-y-4">
            <div class="card border-0 overflow-hidden">
                <div class="card-body p-0">
                    <div class="border-bottom px-4 py-3">
                        <h4 class="mb-1 text-lg font-semibold text-slate-900">{{ $hasWarehouseSchema ? 'Stock por almacen' : 'Stock por sucursal' }}</h4>
                        <p class="mb-0 text-sm text-muted">
                            {{ $hasWarehouseSchema ? 'Vista operativa del stock, minimo y costos por cada almacen.' : 'Vista operativa del stock actual por sucursal mientras se aplican las nuevas migraciones.' }}
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Sucursal</th>
                                    @if($hasWarehouseSchema)
                                        <th>Almacen</th>
                                    @endif
                                    <th>SKU</th>
                                    <th>Producto</th>
                                    <th>Categoria</th>
                                    <th>Stock</th>
                                    <th>Minimo</th>
                                    @if($hasWarehouseSchema)
                                        <th>Costo promedio</th>
                                        <th>Ultimo costo</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($stocks as $stock)
                                    <tr wire:key="inventory-stock-{{ $stock->id }}">
                                        <td>{{ $stock->branch?->name ?? '-' }}</td>
                                        @if($hasWarehouseSchema)
                                            <td>{{ $stock->warehouse?->name ?? '-' }}</td>
                                        @endif
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
                                        @if($hasWarehouseSchema)
                                            <td>S/ {{ number_format((float) ($stock->average_cost ?? 0), 4) }}</td>
                                            <td>S/ {{ number_format((float) ($stock->last_cost ?? 0), 4) }}</td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $hasWarehouseSchema ? 9 : 6 }}" class="py-5 text-center text-muted">No hay registros de inventario para mostrar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($canViewKardex)
                <div class="card border-0 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="border-bottom px-4 py-3">
                            <h4 class="mb-1 text-lg font-semibold text-slate-900">Kardex valorizado reciente</h4>
                            <p class="mb-0 text-sm text-muted">Entradas, salidas y ajustes con stock antes/despues, costo unitario y costo promedio aplicado.</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @forelse($movements as $movement)
                                <div class="px-4 py-3" wire:key="inventory-movement-{{ $movement->id }}">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="fw-semibold text-slate-900">{{ $movement->product?->name ?? 'Producto' }}</div>
                                            <div class="text-sm text-muted">
                                                {{ $movement->branch?->name ?? 'Sin sucursal' }}
                                                @if($movement->warehouse)
                                                    - {{ $movement->warehouse->name }}
                                                @endif
                                                - {{ $movement->product?->sku ?? '-' }}
                                            </div>
                                        </div>
                                        <span class="badge {{ $movement->quantity >= 0 ? 'bg-success' : 'bg-danger' }}">
                                            {{ $movement->quantity >= 0 ? '+' : '' }}{{ $movement->quantity }}
                                        </span>
                                    </div>
                                    <div class="mt-2 d-flex flex-wrap gap-2 text-sm text-muted">
                                        <span>{{ strtoupper(str_replace('_', ' ', $movement->movement_type)) }}</span>
                                        <span>{{ $movement->reason ? strtoupper(str_replace('_', ' ', $movement->reason)) : 'SIN MOTIVO' }}</span>
                                        <span>{{ $movement->stock_before }} -> {{ $movement->stock_after }}</span>
                                        <span>CPU S/ {{ number_format((float) ($movement->unit_cost ?? 0), 4) }}</span>
                                        <span>CPP S/ {{ number_format((float) ($movement->average_cost_before ?? 0), 4) }} -> {{ number_format((float) ($movement->average_cost_after ?? 0), 4) }}</span>
                                    </div>
                                    <div class="mt-2 text-sm text-muted">
                                        {{ $movement->reference_code ?: 'Sin referencia' }}
                                        @if($movement->actor)
                                            - {{ $movement->actor->name }}
                                        @endif
                                        - {{ $movement->created_at?->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-5 text-center text-muted">Todavia no hay movimientos registrados.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="card border-0 overflow-hidden">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                        <div>
                            <h4 class="mb-1 text-lg font-semibold text-slate-900">Registrar guia</h4>
                            <p class="mb-0 text-sm text-muted">Confirma una guia de ingreso o salida y actualiza stock y kardex en una sola transaccion.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <flux:button type="button" variant="{{ $documentType === 'inbound' ? 'primary' : 'outline' }}" size="sm" wire:click="$set('documentType', 'inbound')">Guia ingreso</flux:button>
                            <flux:button type="button" variant="{{ $documentType === 'outbound' ? 'primary' : 'outline' }}" size="sm" wire:click="$set('documentType', 'outbound')">Guia salida</flux:button>
                        </div>
                    </div>

                    @if($hasWarehouseSchema)
                        @if($canManageDocuments)
                            <form wire:submit="saveDocument" class="mt-4 space-y-4">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="form-label">Sucursal</label>
                                        <select wire:model.live="documentBranchId" class="form-select" @disabled(in_array($scopeLevel, ['branch', 'own'], true))>
                                            <option value="">Selecciona</option>
                                            @foreach($branches as $branch)
                                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('documentBranchId') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div>
                                        <label class="form-label">Almacen</label>
                                        <select wire:model.live="documentWarehouseId" class="form-select">
                                            <option value="">Selecciona</option>
                                            @foreach($documentWarehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                                            @endforeach
                                        </select>
                                        @error('documentWarehouseId') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="form-label">Motivo</label>
                                        <input type="text" wire:model="documentReason" class="form-control" placeholder="Compra, devolucion, merma, consumo interno">
                                        @error('documentReason') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    <div>
                                        <label class="form-label">Referencia externa</label>
                                        <input type="text" wire:model="documentExternalReference" class="form-control" placeholder="Serie, factura proveedor o ticket interno">
                                        @error('documentExternalReference') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    @foreach($documentItems as $index => $item)
                                        <div class="rounded-4 border border-slate-200 p-3" wire:key="document-item-{{ $index }}">
                                            <div class="grid gap-3 lg:grid-cols-[1.5fr,120px,140px,auto] lg:items-end">
                                                <div>
                                                    <label class="form-label">Producto</label>
                                                    <select wire:model="documentItems.{{ $index }}.product_id" class="form-select">
                                                        <option value="">Selecciona</option>
                                                        @foreach($documentProducts as $product)
                                                            <option value="{{ $product->id }}">{{ $product->name }} - {{ $product->sku }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('documentItems.'.$index.'.product_id') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                                </div>
                                                <div>
                                                    <label class="form-label">Cantidad</label>
                                                    <input type="number" min="1" wire:model="documentItems.{{ $index }}.quantity" class="form-control">
                                                    @error('documentItems.'.$index.'.quantity') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                                </div>
                                                <div>
                                                    <label class="form-label">{{ $documentType === 'inbound' ? 'Costo unitario' : 'Costo aplicado' }}</label>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.0001"
                                                        wire:model="documentItems.{{ $index }}.unit_cost"
                                                        class="form-control"
                                                        @disabled($documentType === 'outbound')
                                                        placeholder="{{ $documentType === 'inbound' ? '0.0000' : 'Promedio del almacen' }}"
                                                    >
                                                    @error('documentItems.'.$index.'.unit_cost') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="d-flex justify-content-end">
                                                    @if(count($documentItems) > 1)
                                                        <flux:button type="button" variant="ghost" size="sm" wire:click="removeDocumentItem({{ $index }})">Quitar</flux:button>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <label class="form-label">Nota item</label>
                                                <input type="text" wire:model="documentItems.{{ $index }}.notes" class="form-control" placeholder="Observacion puntual del producto">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="d-flex justify-content-between gap-3 align-items-center">
                                    <flux:button type="button" variant="outline" icon="plus" wire:click="addDocumentItem">Agregar item</flux:button>
                                    <div class="text-sm text-muted">
                                        {{ $documentType === 'inbound' ? 'La guia recalculara el costo promedio del almacen con cada ingreso confirmado.' : 'La guia consumira el costo promedio vigente del almacen y bloqueara stock para evitar concurrencia.' }}
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label">Notas generales</label>
                                    <textarea wire:model="documentNotes" rows="3" class="form-control" placeholder="Detalle adicional de la operacion"></textarea>
                                    @error('documentNotes') <div class="mt-1 text-sm text-danger">{{ $message }}</div> @enderror
                                </div>

                                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
                                    <flux:button type="button" variant="ghost" wire:click="resetDocumentForm">Limpiar guia</flux:button>
                                    <flux:button type="submit" variant="primary" icon="document-check" wire:loading.attr="disabled">Registrar guia y actualizar stock</flux:button>
                                </div>
                            </form>
                        @else
                            <div class="mt-4 rounded-4 border border-dashed p-4 text-sm text-muted">
                                Tu rol puede consultar inventario, pero aun no puede registrar ni confirmar guias documentales.
                            </div>
                        @endif
                    @else
                        <div class="mt-4 rounded-4 border border-dashed p-4 text-sm text-muted">
                            Ejecuta primero las nuevas migraciones de almacenes, stock por almacen y documentos para habilitar esta seccion.
                        </div>
                    @endif
                </div>
            </div>

            @if($canViewDocuments)
                <div class="card border-0 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="border-bottom px-4 py-3">
                            <h4 class="mb-1 text-lg font-semibold text-slate-900">Guias recientes</h4>
                            <p class="mb-0 text-sm text-muted">Ultimas guias confirmadas o en borrador con referencia, almacen y responsable.</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @forelse($recentDocuments as $document)
                                <div class="px-4 py-3" wire:key="inventory-document-{{ $document->id }}">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="fw-semibold text-slate-900">{{ $document->code }}</div>
                                            <div class="text-sm text-muted">{{ strtoupper($document->document_type) }} - {{ $document->branch?->name ?? 'Sin sucursal' }} - {{ $document->warehouse?->name ?? 'Sin almacen' }}</div>
                                        </div>
                                        <span class="badge {{ $document->status === 'confirmed' ? 'bg-success' : ($document->status === 'draft' ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ strtoupper($document->status) }}</span>
                                    </div>
                                    <div class="mt-2 text-sm text-muted">
                                        @php($firstItem = $document->items->first())
                                        {{ $firstItem?->product?->name ?? 'Sin producto' }}
                                        @if($document->items->count() > 1)
                                            + {{ $document->items->count() - 1 }} item(s)
                                        @endif
                                    </div>
                                    <div class="mt-2 text-sm text-muted">
                                        {{ $document->creator?->name ?? 'Sistema' }} - {{ $document->issued_at?->format('d/m/Y H:i') ?? $document->created_at?->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-5 text-center text-muted">Todavia no hay guias registradas.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            @if($canViewTransfers || $canCreateTransfer)
                <div class="card border-0 overflow-hidden">
                    <div class="card-body">
                        <div class="mb-3">
                            <h4 class="mb-1 text-lg font-semibold text-slate-900">Transferencia entre sucursales</h4>
                            <p class="mb-0 text-sm text-muted">Mantiene el flujo actual de salida en origen y entrada en destino entre sucursales.</p>
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
                                            <option value="{{ $product->id }}">{{ $product->name }} - {{ $product->sku }}</option>
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
                                    <flux:button type="submit" variant="primary" icon="arrows-right-left" wire:loading.attr="disabled">Registrar transferencia</flux:button>
                                </div>
                            </form>
                        @else
                            <div class="rounded-4 border border-dashed p-4 text-sm text-muted">Tu rol puede revisar transferencias, pero no registrar nuevas operaciones entre sucursales.</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div>
        {{ $stocks->links() }}
    </div>
</div>

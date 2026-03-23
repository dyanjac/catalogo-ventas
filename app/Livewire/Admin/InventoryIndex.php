<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Services\InventoryDocumentService;
use Modules\Catalog\Services\InventoryTransferService;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityAuthorizationService;
use Modules\Security\Services\SecurityBranchContextService;
use Modules\Security\Services\SecurityScopeService;

class InventoryIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'branch_id', history: true, keep: true)]
    public string $branchId = '';

    #[Url(as: 'warehouse_id', history: true, keep: true)]
    public string $warehouseId = '';

    public string $documentType = 'inbound';

    public string $documentBranchId = '';

    public string $documentWarehouseId = '';

    public string $documentReason = '';

    public string $documentExternalReference = '';

    public string $documentNotes = '';

    public array $documentItems = [];

    public string $transferSourceBranchId = '';

    public string $transferDestinationBranchId = '';

    public string $transferProductId = '';

    public string $transferQuantity = '1';

    public string $transferNotes = '';

    public ?string $flashMessage = null;

    public string $flashTone = 'success';

    public function mount(): void
    {
        if ($this->documentItems === []) {
            $this->documentItems = [$this->emptyDocumentItem()];
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBranchId(): void
    {
        $this->resetPage();
    }

    public function updatingWarehouseId(): void
    {
        $this->resetPage();
    }

    public function updatedDocumentType(): void
    {
        if ($this->documentType === 'outbound') {
            foreach ($this->documentItems as $index => $item) {
                $this->documentItems[$index]['unit_cost'] = '';
            }
        }
    }

    public function updatedDocumentBranchId(): void
    {
        $this->documentWarehouseId = '';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'branchId', 'warehouseId']);
        $this->resetPage();
    }

    public function addDocumentItem(): void
    {
        $this->documentItems[] = $this->emptyDocumentItem();
    }

    public function removeDocumentItem(int $index): void
    {
        if (count($this->documentItems) <= 1) {
            return;
        }

        unset($this->documentItems[$index]);
        $this->documentItems = array_values($this->documentItems);
    }

    public function resetDocumentForm(): void
    {
        $this->documentType = 'inbound';
        $this->documentWarehouseId = '';
        $this->documentReason = '';
        $this->documentExternalReference = '';
        $this->documentNotes = '';
        $this->documentItems = [$this->emptyDocumentItem()];
    }

    public function saveDocument(
        InventoryDocumentService $documents,
        SecurityAuthorizationService $authorization,
        SecurityScopeService $scopeService,
        SecurityBranchContextService $branchContext
    ): void {
        $actor = auth()->user();

        abort_unless(
            $authorization->hasPermission($actor, 'inventory.documents.create')
                && $authorization->hasPermission($actor, 'inventory.documents.confirm'),
            403
        );

        if (! $this->hasWarehouseSchema()) {
            throw ValidationException::withMessages([
                'document' => 'Primero debes ejecutar las migraciones de almacenes y documentos de inventario.',
            ]);
        }

        $validated = $this->validate([
            'documentType' => ['required', Rule::in(['inbound', 'outbound'])],
            'documentBranchId' => ['required', 'integer', 'exists:security_branches,id'],
            'documentWarehouseId' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'documentReason' => ['nullable', 'string', 'max:60'],
            'documentExternalReference' => ['nullable', 'string', 'max:80'],
            'documentNotes' => ['nullable', 'string', 'max:1000'],
            'documentItems' => ['required', 'array', 'min:1'],
            'documentItems.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'documentItems.*.quantity' => ['required', 'integer', 'min:1'],
            'documentItems.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'documentItems.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $scopeLevel = $scopeService->scopeLevelForModule($actor, 'inventory');
        $actorBranchId = $branchContext->currentBranchId($actor);

        if (in_array($scopeLevel, ['branch', 'own'], true) && (int) $validated['documentBranchId'] !== (int) $actorBranchId) {
            throw ValidationException::withMessages([
                'documentBranchId' => 'Tu perfil solo puede registrar guias para la sucursal asignada.',
            ]);
        }

        $warehouse = InventoryWarehouse::query()
            ->whereKey((int) $validated['documentWarehouseId'])
            ->where('branch_id', (int) $validated['documentBranchId'])
            ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'documentWarehouseId' => 'El almacen seleccionado no pertenece a la sucursal indicada.',
            ]);
        }

        if (! $scopeService->canAccessInventoryWarehouse($actor, $warehouse, 'inventory')) {
            throw ValidationException::withMessages([
                'documentWarehouseId' => 'No tienes permisos para operar el almacen seleccionado.',
            ]);
        }

        $items = collect($validated['documentItems'])
            ->map(function (array $item) use ($validated): array {
                return [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_cost' => $validated['documentType'] === 'inbound' && $item['unit_cost'] !== '' && $item['unit_cost'] !== null
                        ? round((float) $item['unit_cost'], 4)
                        : null,
                    'notes' => $item['notes'] ?? null,
                ];
            })
            ->all();

        $document = $documents->createDraft([
            'document_type' => $validated['documentType'],
            'branch_id' => (int) $validated['documentBranchId'],
            'warehouse_id' => (int) $validated['documentWarehouseId'],
            'reason' => $validated['documentReason'] !== '' ? $validated['documentReason'] : null,
            'external_reference' => $validated['documentExternalReference'] !== '' ? $validated['documentExternalReference'] : null,
            'issued_at' => now(),
            'created_by' => auth()->id(),
            'notes' => $validated['documentNotes'] !== '' ? $validated['documentNotes'] : null,
            'items' => $items,
        ]);

        $confirmed = $documents->confirm($document->id, auth()->id());

        if (! $scopeService->canAccessInventoryDocument($actor, $confirmed, 'inventory')) {
            throw ValidationException::withMessages([
                'document' => 'La guia fue generada pero quedo fuera de tu alcance operativo.',
            ]);
        }

        $this->resetDocumentForm();
        $this->flashTone = 'success';
        $this->flashMessage = 'Guia registrada correctamente: '.$confirmed->code;
    }

    public function saveTransfer(
        InventoryTransferService $transfers,
        SecurityAuthorizationService $authorization,
        SecurityScopeService $scopeService,
        SecurityBranchContextService $branchContext
    ): void {
        abort_unless($authorization->hasPermission(auth()->user(), 'inventory.transfers.create'), 403);

        $validated = $this->validate([
            'transferSourceBranchId' => ['required', 'integer', 'exists:security_branches,id'],
            'transferDestinationBranchId' => ['required', 'integer', 'exists:security_branches,id', 'different:transferSourceBranchId'],
            'transferProductId' => ['required', 'integer', 'exists:products,id'],
            'transferQuantity' => ['required', 'integer', 'min:1'],
            'transferNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = auth()->user();
        $scopeLevel = $scopeService->scopeLevelForModule($actor, 'inventory');
        $actorBranchId = $branchContext->currentBranchId($actor);

        if (in_array($scopeLevel, ['branch', 'own'], true) && (int) $validated['transferSourceBranchId'] !== (int) $actorBranchId) {
            throw ValidationException::withMessages([
                'transferSourceBranchId' => 'Tu perfil solo puede transferir desde la sucursal asignada.',
            ]);
        }

        $product = Product::query()->findOrFail((int) $validated['transferProductId']);

        $transfer = $transfers->transferProduct(
            $product,
            (int) $validated['transferSourceBranchId'],
            (int) $validated['transferDestinationBranchId'],
            (int) $validated['transferQuantity'],
            [
                'created_by' => auth()->id(),
                'notes' => $validated['transferNotes'] !== '' ? $validated['transferNotes'] : null,
            ]
        );

        $this->transferDestinationBranchId = '';
        $this->transferProductId = '';
        $this->transferQuantity = '1';
        $this->transferNotes = '';
        $this->flashTone = 'success';
        $this->flashMessage = 'Transferencia registrada: '.$transfer->code;
    }

    public function render(
        SecurityScopeService $scopeService,
        SecurityBranchContextService $branchContext,
        SecurityAuthorizationService $authorization
    ) {
        $actor = auth()->user();
        $scopeLevel = $scopeService->scopeLevelForModule($actor, 'inventory');
        $actorBranchId = $branchContext->currentBranchId($actor);
        $effectiveBranchId = in_array($scopeLevel, ['branch', 'own'], true)
            ? $actorBranchId
            : ($this->branchId !== '' ? (int) $this->branchId : null);

        $canManageDocuments = $authorization->hasPermission($actor, 'inventory.documents.create')
            && $authorization->hasPermission($actor, 'inventory.documents.confirm');
        $canViewDocuments = $authorization->hasPermission($actor, 'inventory.documents.view') || $canManageDocuments;
        $canViewKardex = $authorization->hasPermission($actor, 'inventory.kardex.view');
        $canViewWarehouses = $authorization->hasPermission($actor, 'inventory.warehouses.view') || $canManageDocuments;
        $canCreateTransfer = $authorization->hasPermission($actor, 'inventory.transfers.create');
        $canViewTransfers = $authorization->hasPermission($actor, 'inventory.transfers.view') || $canCreateTransfer;

        if ($this->documentItems === []) {
            $this->documentItems = [$this->emptyDocumentItem()];
        }

        if (in_array($scopeLevel, ['branch', 'own'], true) && $actorBranchId) {
            if ($this->documentBranchId === '') {
                $this->documentBranchId = (string) $actorBranchId;
            }

            if ($this->transferSourceBranchId === '') {
                $this->transferSourceBranchId = (string) $actorBranchId;
            }
        }

        $branches = SecurityBranch::query()
            ->where('is_active', true)
            ->when(in_array($scopeLevel, ['branch', 'own'], true) && $actorBranchId, fn ($query) => $query->whereKey($actorBranchId))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $hasWarehouseSchema = $this->hasWarehouseSchema();

        $stocks = $hasWarehouseSchema
            ? $scopeService->scopeInventoryStocks(
                ProductWarehouseStock::query()
                    ->with(['product.category', 'branch', 'warehouse'])
                    ->when(trim($this->search) !== '', function ($query): void {
                        $search = trim($this->search);
                        $query->whereHas('product', function ($productQuery) use ($search): void {
                            $productQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        });
                    })
                    ->when($effectiveBranchId, fn ($query) => $query->where('branch_id', $effectiveBranchId))
                    ->when($this->warehouseId !== '', fn ($query) => $query->where('warehouse_id', (int) $this->warehouseId)),
                $actor,
                'inventory'
            )->orderByDesc('stock')->paginate(15)
            : $scopeService->scopeInventoryStocks(
                ProductBranchStock::query()
                    ->with(['product.category', 'branch'])
                    ->when(trim($this->search) !== '', function ($query): void {
                        $search = trim($this->search);
                        $query->whereHas('product', function ($productQuery) use ($search): void {
                            $productQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        });
                    })
                    ->when($effectiveBranchId, fn ($query) => $query->where('branch_id', $effectiveBranchId)),
                $actor,
                'inventory'
            )->orderByDesc('stock')->paginate(15);

        $movements = $canViewKardex
            ? $scopeService->scopeInventoryMovements(
                InventoryMovement::query()
                    ->with(['product.category', 'branch', 'warehouse', 'actor'])
                    ->when(trim($this->search) !== '', function ($query): void {
                        $search = trim($this->search);
                        $query->where(function ($subQuery) use ($search): void {
                            $subQuery->where('reference_code', 'like', "%{$search}%")
                                ->orWhereHas('product', function ($productQuery) use ($search): void {
                                    $productQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('sku', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->when($effectiveBranchId, fn ($query) => $query->where('branch_id', $effectiveBranchId))
                    ->when($this->warehouseId !== '' && $hasWarehouseSchema, fn ($query) => $query->where('warehouse_id', (int) $this->warehouseId)),
                $actor,
                'inventory'
            )->latest('id')->take(12)->get()
            : collect();

        $recentDocuments = collect();
        $warehouses = collect();
        $documentWarehouses = collect();
        $documentProducts = collect();

        if ($hasWarehouseSchema && $canViewWarehouses) {
            $warehouses = $scopeService->scopeInventoryWarehouses(
                InventoryWarehouse::query()
                    ->with('branch')
                    ->where('is_active', true)
                    ->when($effectiveBranchId, fn ($query) => $query->where('branch_id', $effectiveBranchId)),
                $actor,
                'inventory'
            )->orderBy('name')->get();
        }

        if ($hasWarehouseSchema && $canManageDocuments) {
            $documentBranchId = $this->documentBranchId !== '' ? (int) $this->documentBranchId : $effectiveBranchId;

            $documentWarehouses = $scopeService->scopeInventoryWarehouses(
                InventoryWarehouse::query()
                    ->where('is_active', true)
                    ->when($documentBranchId, fn ($query) => $query->where('branch_id', $documentBranchId)),
                $actor,
                'inventory'
            )->orderBy('name')->get();

            $documentProducts = Product::query()
                ->where('is_active', true)
                ->when($this->documentType === 'outbound' && $this->documentWarehouseId !== '', function ($query): void {
                    $warehouseId = (int) $this->documentWarehouseId;
                    $query->whereHas('warehouseStocks', function ($stockQuery) use ($warehouseId): void {
                        $stockQuery->where('warehouse_id', $warehouseId)
                            ->where('is_active', true)
                            ->where('stock', '>', 0);
                    });
                })
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'purchase_price', 'average_price']);
        }

        if ($hasWarehouseSchema && $canViewDocuments) {
            $documentQuery = $scopeService->scopeInventoryDocuments(
                InventoryDocument::query()
                    ->with(['warehouse', 'branch', 'items.product', 'creator', 'confirmer'])
                    ->when(trim($this->search) !== '', function ($query): void {
                        $search = trim($this->search);
                        $query->where(function ($subQuery) use ($search): void {
                            $subQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('external_reference', 'like', "%{$search}%")
                                ->orWhereHas('items.product', function ($productQuery) use ($search): void {
                                    $productQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('sku', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->when($effectiveBranchId, fn ($query) => $query->where('branch_id', $effectiveBranchId))
                    ->when($this->warehouseId !== '', fn ($query) => $query->where('warehouse_id', (int) $this->warehouseId)),
                $actor,
                'inventory'
            );

            $recentDocuments = $documentQuery->latest('id')->take(8)->get();
        }

        $transferProducts = Product::query()
            ->whereHas('branchStocks', function ($query) use ($scopeLevel, $actorBranchId): void {
                $query->where('is_active', true)->where('stock', '>', 0);

                if (in_array($scopeLevel, ['branch', 'own'], true) && $actorBranchId) {
                    $query->where('branch_id', $actorBranchId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        $recentTransfers = $canViewTransfers
            ? InventoryTransfer::query()
                ->with(['sourceBranch', 'destinationBranch', 'items.product', 'creator'])
                ->when($effectiveBranchId, function ($query) use ($effectiveBranchId): void {
                    $query->where(function ($subQuery) use ($effectiveBranchId): void {
                        $subQuery->where('source_branch_id', $effectiveBranchId)
                            ->orWhere('destination_branch_id', $effectiveBranchId);
                    });
                })
                ->latest('id')
                ->take(8)
                ->get()
            : collect();

        return view('livewire.admin.inventory-index', [
            'hasWarehouseSchema' => $hasWarehouseSchema,
            'stocks' => $stocks,
            'movements' => $movements,
            'recentDocuments' => $recentDocuments,
            'recentTransfers' => $recentTransfers,
            'transferProducts' => $transferProducts,
            'documentProducts' => $documentProducts,
            'warehouses' => $warehouses,
            'documentWarehouses' => $documentWarehouses,
            'branches' => $branches,
            'canManageDocuments' => $canManageDocuments,
            'canViewDocuments' => $canViewDocuments,
            'canViewKardex' => $canViewKardex,
            'canViewWarehouses' => $canViewWarehouses,
            'canCreateTransfer' => $canCreateTransfer,
            'canViewTransfers' => $canViewTransfers,
            'scopeLevel' => $scopeLevel,
            'actorBranchId' => $actorBranchId,
            'effectiveBranchId' => $effectiveBranchId,
        ]);
    }

    private function emptyDocumentItem(): array
    {
        return [
            'product_id' => '',
            'quantity' => '1',
            'unit_cost' => '',
            'notes' => '',
        ];
    }

    private function hasWarehouseSchema(): bool
    {
        return Schema::hasTable('inventory_warehouses')
            && Schema::hasTable('product_warehouse_stocks')
            && Schema::hasTable('inventory_documents')
            && Schema::hasTable('inventory_document_items');
    }
}

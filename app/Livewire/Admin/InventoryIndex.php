<?php

namespace App\Livewire\Admin;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Services\InventoryTransferService;
use Modules\Catalog\Services\ProductInventoryService;
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

    public ?int $selectedStockId = null;

    public string $adjustmentTargetStock = '';

    public string $adjustmentReason = 'manual_adjustment';

    public string $adjustmentNotes = '';

    public string $transferSourceBranchId = '';

    public string $transferDestinationBranchId = '';

    public string $transferProductId = '';

    public string $transferQuantity = '1';

    public string $transferNotes = '';

    public ?string $flashMessage = null;

    public string $flashTone = 'success';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBranchId(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'branchId']);
        $this->resetPage();
    }

    public function selectStock(int $stockId, SecurityScopeService $scopeService): void
    {
        $stock = ProductBranchStock::query()->with(['product', 'branch'])->findOrFail($stockId);

        abort_unless($scopeService->canAccessInventoryStock(auth()->user(), $stock, 'inventory'), 403);

        $this->selectedStockId = $stock->id;
        $this->adjustmentTargetStock = (string) $stock->stock;
        $this->adjustmentReason = 'manual_adjustment';
        $this->adjustmentNotes = '';
        $this->flashMessage = null;
    }

    public function saveAdjustment(
        ProductInventoryService $inventory,
        SecurityAuthorizationService $authorization,
        SecurityScopeService $scopeService
    ): void {
        abort_unless($authorization->hasPermission(auth()->user(), 'inventory.adjustments.update'), 403);

        $validated = $this->validate([
            'selectedStockId' => ['required', 'integer', 'exists:product_branch_stocks,id'],
            'adjustmentTargetStock' => ['required', 'integer', 'min:0'],
            'adjustmentReason' => ['required', 'string', 'max:60'],
            'adjustmentNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $stock = ProductBranchStock::query()->with(['product', 'branch'])->findOrFail((int) $validated['selectedStockId']);

        abort_unless($scopeService->canAccessInventoryStock(auth()->user(), $stock, 'inventory'), 403);

        if (! $stock->product) {
            throw ValidationException::withMessages([
                'selectedStockId' => 'El registro de stock no tiene un producto asociado.',
            ]);
        }

        $inventory->adjustBranchStock($stock->product, (int) $stock->branch_id, (int) $validated['adjustmentTargetStock'], [
            'reason' => $validated['adjustmentReason'],
            'notes' => $validated['adjustmentNotes'] !== '' ? $validated['adjustmentNotes'] : null,
            'performed_by' => auth()->id(),
            'reference_type' => ProductBranchStock::class,
            'reference_id' => $stock->id,
            'reference_code' => 'STOCK-'.$stock->id,
            'meta' => [
                'channel' => 'admin_inventory',
            ],
        ]);

        $this->selectStock($stock->id, $scopeService);
        $this->flashTone = 'success';
        $this->flashMessage = 'Ajuste de inventario registrado correctamente.';
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
        $effectiveBranchId = in_array($scopeLevel, ['branch', 'own'], true) ? $actorBranchId : ($this->branchId !== '' ? (int) $this->branchId : null);

        if (in_array($scopeLevel, ['branch', 'own'], true) && $actorBranchId && $this->transferSourceBranchId === '') {
            $this->transferSourceBranchId = (string) $actorBranchId;
        }

        $query = ProductBranchStock::query()
            ->with(['product.category', 'branch'])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($effectiveBranchId, fn ($query) => $query->where('branch_id', $effectiveBranchId));

        $movementsQuery = InventoryMovement::query()
            ->with(['product.category', 'branch', 'actor'])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($effectiveBranchId, fn ($query) => $query->where('branch_id', $effectiveBranchId));

        $transferQuery = InventoryTransfer::query()
            ->with(['sourceBranch', 'destinationBranch', 'items.product', 'creator'])
            ->when($effectiveBranchId, function ($query) use ($effectiveBranchId) {
                $query->where(function ($subQuery) use ($effectiveBranchId) {
                    $subQuery->where('source_branch_id', $effectiveBranchId)
                        ->orWhere('destination_branch_id', $effectiveBranchId);
                });
            });

        $stocks = $scopeService
            ->scopeInventoryStocks($query, $actor, 'inventory')
            ->orderByDesc('stock')
            ->paginate(15);

        $movements = $scopeService
            ->scopeInventoryMovements($movementsQuery, $actor, 'inventory')
            ->latest('id')
            ->take(12)
            ->get();

        $selectedStock = $this->selectedStockId
            ? $scopeService->scopeInventoryStocks(
                ProductBranchStock::query()->with(['product.category', 'branch']),
                $actor,
                'inventory'
            )->find($this->selectedStockId)
            : null;

        if (! $selectedStock && $this->selectedStockId !== null) {
            $this->selectedStockId = null;
            $this->adjustmentTargetStock = '';
            $this->adjustmentNotes = '';
        }

        $transferProducts = Product::query()
            ->whereHas('branchStocks', function ($query) use ($scopeLevel, $actorBranchId) {
                $query->where('is_active', true)->where('stock', '>', 0);

                if (in_array($scopeLevel, ['branch', 'own'], true) && $actorBranchId) {
                    $query->where('branch_id', $actorBranchId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        $recentTransfers = $transferQuery->latest('id')->take(8)->get();

        return view('livewire.admin.inventory-index', [
            'stocks' => $stocks,
            'movements' => $movements,
            'recentTransfers' => $recentTransfers,
            'transferProducts' => $transferProducts,
            'selectedStock' => $selectedStock,
            'canAdjustInventory' => $authorization->hasPermission($actor, 'inventory.adjustments.update'),
            'canCreateTransfer' => $authorization->hasPermission($actor, 'inventory.transfers.create'),
            'branches' => SecurityBranch::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'scopeLevel' => $scopeLevel,
            'actorBranchId' => $actorBranchId,
            'effectiveBranchId' => $effectiveBranchId,
        ]);
    }
}

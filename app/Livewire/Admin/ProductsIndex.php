<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\UnitMeasure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Services\ProductInventoryService;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityAuthorizationService;
use Modules\Security\Services\SecurityBranchContextService;
use Modules\Security\Services\SecurityScopeService;

class ProductsIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'category_id', history: true, keep: true)]
    public string $categoryId = '';

    #[Url(as: 'unit_measure_id', history: true, keep: true)]
    public string $unitMeasureId = '';

    #[Url(as: 'is_active', history: true, keep: true)]
    public string $isActive = '';

    public ?int $selectedProductId = null;

    public array $assignmentBranchStates = [];

    public array $assignmentBranchMinStocks = [];

    public array $assignmentWarehouseStates = [];

    public array $assignmentWarehouseMinStocks = [];

    public ?string $flashMessage = null;

    public string $flashTone = 'success';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedUnitMeasureId(): void
    {
        $this->resetPage();
    }

    public function updatedIsActive(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryId', 'unitMeasureId', 'isActive']);
        $this->resetPage();
    }

    public function selectProductForAssignments(
        int $productId,
        SecurityScopeService $scopeService,
        SecurityBranchContextService $branchContext
    ): void {
        $product = Product::query()
            ->forCurrentOrganization()
            ->with([
                'branchStocks.branch',
                'warehouseStocks.warehouse.branch',
            ])
            ->findOrFail($productId);

        abort_unless($scopeService->canAccessProduct(auth()->user(), $product, 'catalog'), 403);

        $this->selectedProductId = $product->id;
        $this->loadAssignmentState(
            $product,
            $this->availableBranches($scopeService, $branchContext),
            $this->availableWarehouses($scopeService, $branchContext)
        );
        $this->flashMessage = null;
    }

    public function saveAssignments(
        ProductInventoryService $inventory,
        SecurityAuthorizationService $authorization,
        SecurityScopeService $scopeService,
        SecurityBranchContextService $branchContext
    ): void {
        $actor = auth()->user();

        abort_unless($authorization->hasPermission($actor, 'catalog.products.update'), 403);

        if (! $this->selectedProductId) {
            throw ValidationException::withMessages([
                'product' => 'Selecciona un producto para configurar su cobertura operativa.',
            ]);
        }

        $product = Product::query()
            ->forCurrentOrganization()
            ->with(['branchStocks', 'warehouseStocks'])
            ->findOrFail($this->selectedProductId);

        abort_unless($scopeService->canAccessProduct($actor, $product, 'catalog'), 403);

        $branches = $this->availableBranches($scopeService, $branchContext);
        $warehouses = $this->availableWarehouses($scopeService, $branchContext)->groupBy('branch_id');

        $this->validate([
            'assignmentBranchStates' => ['array'],
            'assignmentBranchMinStocks' => ['array'],
            'assignmentBranchMinStocks.*' => ['nullable', 'integer', 'min:0'],
            'assignmentWarehouseStates' => ['array'],
            'assignmentWarehouseMinStocks' => ['array'],
            'assignmentWarehouseMinStocks.*' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($branches as $branch) {
            $branchId = (int) $branch->id;
            $branchEnabled = (bool) ($this->assignmentBranchStates[$branchId] ?? false);
            $branchMinStock = max(0, (int) ($this->assignmentBranchMinStocks[$branchId] ?? 0));
            $branchWarehouses = $warehouses->get($branchId, collect());

            foreach ($branchWarehouses as $warehouse) {
                $warehouseEnabled = (bool) ($this->assignmentWarehouseStates[$warehouse->id] ?? false);
                $existingWarehouseStock = ProductWarehouseStock::query()
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->first();

                if (! $warehouseEnabled && $existingWarehouseStock) {
                    if ((int) $existingWarehouseStock->stock > 0) {
                        throw ValidationException::withMessages([
                            'assignmentWarehouseStates.'.$warehouse->id => "No puedes desactivar el almacen {$warehouse->name} mientras el producto tenga stock registrado alli.",
                        ]);
                    }

                    if ($this->hasDraftDocumentsForWarehouse($product->id, $warehouse->id)) {
                        throw ValidationException::withMessages([
                            'assignmentWarehouseStates.'.$warehouse->id => "No puedes desactivar el almacen {$warehouse->name} mientras existan guias en borrador para este producto.",
                        ]);
                    }
                }
            }

            $existingBranchStock = ProductBranchStock::query()
                ->where('product_id', $product->id)
                ->where('branch_id', $branchId)
                ->first();

            $enabledWarehouseExists = $branchWarehouses->contains(function ($warehouse): bool {
                return (bool) ($this->assignmentWarehouseStates[$warehouse->id] ?? false);
            });

            if (! $branchEnabled && $enabledWarehouseExists) {
                throw ValidationException::withMessages([
                    'assignmentBranchStates.'.$branchId => "No puedes desactivar la sucursal {$branch->name} mientras tenga almacenes activos para este producto.",
                ]);
            }

            if (! $branchEnabled && $existingBranchStock) {
                if ((int) $existingBranchStock->stock > 0) {
                    throw ValidationException::withMessages([
                        'assignmentBranchStates.'.$branchId => "No puedes desactivar la sucursal {$branch->name} mientras el producto tenga stock disponible en esa sucursal.",
                    ]);
                }

                if ($this->hasDraftDocumentsForBranch($product->id, $branchId)) {
                    throw ValidationException::withMessages([
                        'assignmentBranchStates.'.$branchId => "No puedes desactivar la sucursal {$branch->name} mientras existan guias en borrador para este producto.",
                    ]);
                }
            }

            $branchShouldBeActive = $branchEnabled || $enabledWarehouseExists;
            $branchStock = ProductBranchStock::query()->firstOrNew([
                'product_id' => $product->id,
                'branch_id' => $branchId,
            ]);

            if ($branchStock->exists || $branchShouldBeActive || $branchMinStock > 0) {
                $branchStock->fill([
                    'stock' => (int) ($branchStock->stock ?? 0),
                    'min_stock' => $branchMinStock,
                    'is_active' => $branchShouldBeActive,
                ])->save();
            }

            foreach ($branchWarehouses as $warehouse) {
                $warehouseEnabled = (bool) ($this->assignmentWarehouseStates[$warehouse->id] ?? false);
                $warehouseMinStock = max(0, (int) ($this->assignmentWarehouseMinStocks[$warehouse->id] ?? 0));
                $warehouseStock = ProductWarehouseStock::query()->firstOrNew([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ]);

                if ($warehouseStock->exists || $warehouseEnabled || $warehouseMinStock > 0) {
                    $warehouseStock->fill([
                        'branch_id' => $branchId,
                        'stock' => (int) ($warehouseStock->stock ?? 0),
                        'min_stock' => $warehouseMinStock,
                        'average_cost' => (float) ($warehouseStock->average_cost ?? $product->average_price ?? $product->purchase_price ?? 0),
                        'last_cost' => (float) ($warehouseStock->last_cost ?? $product->purchase_price ?? $product->average_price ?? 0),
                        'is_active' => $warehouseEnabled,
                    ])->save();
                }
            }

            if ($this->hasWarehouseSchema()) {
                $inventory->syncBranchAggregateStock($product, $branchId);
            }
        }

        $inventory->syncAggregateStock($product->fresh());

        $refreshed = Product::query()
            ->forCurrentOrganization()
            ->with(['branchStocks.branch', 'warehouseStocks.warehouse.branch'])
            ->findOrFail($product->id);

        $this->loadAssignmentState(
            $refreshed,
            $branches,
            $this->availableWarehouses($scopeService, $branchContext)
        );

        $this->flashTone = 'success';
        $this->flashMessage = 'Cobertura operativa del producto actualizada correctamente.';
    }

    public function render(
        SecurityScopeService $scopeService,
        SecurityBranchContextService $branchContext,
        ProductInventoryService $inventory,
        SecurityAuthorizationService $authorization
    ) {
        $branchId = $branchContext->currentBranchId(auth()->user());
        $actor = auth()->user();
        $branches = $this->availableBranches($scopeService, $branchContext);
        $warehouses = $this->availableWarehouses($scopeService, $branchContext);
        $hasWarehouseSchema = $this->hasWarehouseSchema();

        $query = Product::query()
            ->forCurrentOrganization()
            ->with([
                'category',
                'unitMeasure',
                'mainImage',
                'branchStocks' => fn ($stock) => $branchId ? $stock->where('branch_id', $branchId)->where('is_active', true) : $stock->where('is_active', true),
            ])
            ->latest('id')
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->when($this->unitMeasureId !== '', fn ($query) => $query->where('unit_measure_id', $this->unitMeasureId))
            ->when($this->isActive !== '', fn ($query) => $query->where('is_active', (bool) $this->isActive));

        $products = $scopeService
            ->scopeProducts($query, auth()->user(), 'catalog')
            ->paginate(12);

        $products->getCollection()->transform(function ($product) use ($inventory, $branchId) {
            $product->effective_stock = $inventory->availableStock($product, $branchId);
            $product->effective_min_stock = $inventory->minimumStock($product, $branchId);

            return $product;
        });

        if (! $this->selectedProductId && $products->count() > 0) {
            $this->selectProductForAssignments((int) $products->items()[0]->id, $scopeService, $branchContext);
        }

        $selectedProduct = $this->selectedProductId
            ? $scopeService->scopeProducts(
                Product::query()->forCurrentOrganization()->with(['category', 'branchStocks.branch', 'warehouseStocks.warehouse.branch']),
                $actor,
                'catalog'
            )->find($this->selectedProductId)
            : null;

        $warehousesByBranch = $warehouses->groupBy('branch_id');

        return view('livewire.admin.products-index', [
            'products' => $products,
            'categories' => Category::query()->forCurrentOrganization()->orderBy('name')->get(),
            'unitMeasures' => UnitMeasure::query()->forCurrentOrganization()->orderBy('name')->get(),
            'activeBranchId' => $branchId,
            'branches' => $branches,
            'warehousesByBranch' => $warehousesByBranch,
            'selectedProduct' => $selectedProduct,
            'hasWarehouseSchema' => $hasWarehouseSchema,
            'canManageAssignments' => $authorization->hasPermission(auth()->user(), 'catalog.products.update'),
        ]);
    }

    private function loadAssignmentState(Product $product, Collection $branches, Collection $warehouses): void
    {
        $this->assignmentBranchStates = [];
        $this->assignmentBranchMinStocks = [];
        $this->assignmentWarehouseStates = [];
        $this->assignmentWarehouseMinStocks = [];

        $branchStocks = $product->branchStocks->keyBy('branch_id');
        $warehouseStocks = $product->warehouseStocks->keyBy('warehouse_id');

        foreach ($branches as $branch) {
            $stock = $branchStocks->get($branch->id);
            $this->assignmentBranchStates[$branch->id] = (bool) ($stock?->is_active ?? false);
            $this->assignmentBranchMinStocks[$branch->id] = (string) (int) ($stock?->min_stock ?? 0);
        }

        foreach ($warehouses as $warehouse) {
            $stock = $warehouseStocks->get($warehouse->id);
            $this->assignmentWarehouseStates[$warehouse->id] = (bool) ($stock?->is_active ?? false);
            $this->assignmentWarehouseMinStocks[$warehouse->id] = (string) (int) ($stock?->min_stock ?? 0);
        }
    }

    private function availableBranches(SecurityScopeService $scopeService, SecurityBranchContextService $branchContext): Collection
    {
        $actor = auth()->user();
        $scopeLevel = $scopeService->scopeLevelForModule($actor, 'inventory');
        $actorBranchId = $branchContext->currentBranchId($actor);

        return $scopeService->scopeBranches(
            SecurityBranch::query()->where('is_active', true),
            $actor,
            'inventory'
        )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function availableWarehouses(SecurityScopeService $scopeService, SecurityBranchContextService $branchContext): Collection
    {
        if (! $this->hasWarehouseSchema()) {
            return collect();
        }

        $actor = auth()->user();
        $branches = $this->availableBranches($scopeService, $branchContext)->pluck('id');

        return $scopeService->scopeInventoryWarehouses(
            InventoryWarehouse::query()
                ->where('is_active', true)
                ->whereIn('branch_id', $branches)
                ->with('branch'),
            $actor,
            'inventory'
        )
            ->orderBy('branch_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    private function hasDraftDocumentsForBranch(int $productId, int $branchId): bool
    {
        return InventoryDocument::query()->forCurrentOrganization()
            ->where('status', 'draft')
            ->where('branch_id', $branchId)
            ->whereHas('items', fn ($query) => $query->where('product_id', $productId))
            ->exists();
    }

    private function hasDraftDocumentsForWarehouse(int $productId, int $warehouseId): bool
    {
        return InventoryDocument::query()->forCurrentOrganization()
            ->where('status', 'draft')
            ->where('warehouse_id', $warehouseId)
            ->whereHas('items', fn ($query) => $query->where('product_id', $productId))
            ->exists();
    }

    private function hasWarehouseSchema(): bool
    {
        return Schema::hasTable('inventory_warehouses')
            && Schema::hasTable('product_warehouse_stocks');
    }
}

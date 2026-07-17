<?php

namespace Modules\Catalog\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityBranchContextService;

class ProductInventoryService
{
    public function __construct(
        private readonly SecurityBranchContextService $branchContext,
        private readonly InventoryMovementService $movements,
        private readonly InventoryBalanceReadService $balanceReader,
    ) {}

    public function syncBranchStock(Product $product, ?int $branchId, int $stock, int $minStock): void
    {
        $branchId ??= $this->branchContext->defaultBranchId();

        if (! $branchId) {
            return;
        }

        ProductBranchStock::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'branch_id' => $branchId,
            ],
            [
                'stock' => 0,
                'min_stock' => max(0, $minStock),
                'is_active' => true,
            ]
        );

        $this->movements->recordAdjustment($product, $branchId, max(0, $stock), [
            'reason_code' => 'manual_adjustment',
            'reason' => 'legacy_sync_branch_stock',
        ]);
    }

    public function syncAggregateStock(Product $product): void
    {
        $totals = ProductBranchStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock),0) as stock_total, COALESCE(SUM(min_stock),0) as min_stock_total')
            ->first();

        $product->forceFill([
            'stock' => (int) ($totals?->stock_total ?? 0),
            'min_stock' => (int) ($totals?->min_stock_total ?? 0),
        ])->save();
    }

    public function syncBranchAggregateStock(Product $product, int $branchId): void
    {
        $totals = ProductWarehouseStock::query()
            ->forCurrentOrganization()
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock),0) as stock_total, COALESCE(SUM(min_stock),0) as min_stock_total')
            ->first();

        $branchStock = ProductBranchStock::query()
            ->forCurrentOrganization()
            ->firstOrNew([
                'product_id' => $product->id,
                'branch_id' => $branchId,
            ]);

        $unallocated = \Modules\Catalog\Entities\InventoryBalance::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->whereNull('warehouse_id')
            ->value('physical_stock');

        if ($unallocated === null) {
            $unallocated = (int) ($branchStock->stock ?? 0) - (int) ($totals?->stock_total ?? 0);
        }

        $hasActiveWarehouses = ProductWarehouseStock::query()
            ->forCurrentOrganization()
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->exists();

        $branchStock->fill([
            'stock' => (int) ($totals?->stock_total ?? 0) + max(0, (int) $unallocated),
            'min_stock' => (int) ($totals?->min_stock_total ?? 0),
            'is_active' => $hasActiveWarehouses ? true : (bool) ($branchStock->is_active ?? false),
        ])->save();

        $this->syncAggregateStock($product->fresh());
    }

    public function availableStock(Product $product, ?int $branchId = null): int
    {
        $branchWasProvided = $branchId !== null;
        $branchId ??= $this->branchContext->currentBranchId();
        if (! $branchWasProvided && $branchId && ! SecurityBranch::query()
            ->where('organization_id', $product->organization_id)
            ->whereKey($branchId)
            ->exists()) {
            $branchId = null;
        }

        if ($this->balanceReader->usesLedger((int) $product->organization_id)) {
            return $branchId
                ? $this->balanceReader->branchAvailableStock((int) $product->organization_id, (int) $product->id, $branchId)
                : $this->balanceReader->productAvailableStock((int) $product->organization_id, (int) $product->id);
        }

        if (! $branchId) {
            return (int) ($product->stock ?? 0);
        }

        if ($product->relationLoaded('branchStocks')) {
            $branchStock = $product->branchStocks
                ->first(fn ($stock) => (int) $stock->branch_id === $branchId && (bool) $stock->is_active);

            return (int) ($branchStock?->stock ?? 0);
        }

        return (int) ($product->branchStocks()->where('branch_id', $branchId)->where('is_active', true)->value('stock') ?? 0);
    }

    public function availableWarehouseStock(Product $product, int $branchId, int $warehouseId): int
    {
        if ($this->balanceReader->usesLedger((int) $product->organization_id)) {
            return $this->balanceReader->warehouseAvailableStock((int) $product->organization_id, (int) $product->id, $branchId, $warehouseId);
        }

        if ($product->relationLoaded('warehouseStocks')) {
            $warehouseStock = $product->warehouseStocks
                ->first(fn ($stock) => (int) $stock->branch_id === $branchId && (int) $stock->warehouse_id === $warehouseId && (bool) $stock->is_active);

            return (int) ($warehouseStock?->stock ?? 0);
        }

        return (int) (ProductWarehouseStock::query()
            ->forCurrentOrganization()
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->value('stock') ?? 0);
    }

    public function minimumStock(Product $product, ?int $branchId = null): int
    {
        $branchId ??= $this->branchContext->currentBranchId();

        if (! $branchId) {
            return (int) ($product->min_stock ?? 0);
        }

        if ($this->balanceReader->usesLedger((int) $product->organization_id)) {
            return $this->balanceReader->branchMinimumStock((int) $product->organization_id, (int) $product->id, $branchId);
        }

        if ($product->relationLoaded('branchStocks')) {
            $branchStock = $product->branchStocks
                ->first(fn ($stock) => (int) $stock->branch_id === $branchId && (bool) $stock->is_active);

            return (int) ($branchStock?->min_stock ?? 0);
        }

        return (int) ($product->branchStocks()->where('branch_id', $branchId)->where('is_active', true)->value('min_stock') ?? 0);
    }

    /**
     * @param  array<int,int>  $productIds
     * @return EloquentCollection<int,ProductBranchStock>
     */
    public function lockBranchStocksForProducts(array $productIds, int $branchId): EloquentCollection
    {
        return ProductBranchStock::query()
            ->forCurrentOrganization()
            ->whereIn('product_id', $productIds)
            ->where('branch_id', $branchId)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');
    }

    public function assertAvailable(Product $product, int $quantity, ?int $branchId = null): void
    {
        $branchId ??= $this->branchContext->currentBranchId();
        $available = $this->availableStock($product, $branchId);

        if ($available < $quantity) {
            throw new \Illuminate\Validation\ValidationException(
                validator: validator([], []),
                response: back()->withErrors([
                    'cart' => ["Stock insuficiente para {$product->name}. Disponible en la sucursal: {$available}."],
                ])
            );
        }
    }

    public function decrementBranchStock(Product $product, int $branchId, int $quantity, array $context = []): void
    {
        $this->movements->recordOutbound($product, $branchId, $quantity, $context);
    }

    public function incrementBranchStock(Product $product, int $branchId, int $quantity, array $context = []): void
    {
        $this->movements->recordInbound($product, $branchId, $quantity, $context);
    }

    public function adjustBranchStock(Product $product, int $branchId, int $targetStock, array $context = []): void
    {
        $this->movements->recordAdjustment($product, $branchId, $targetStock, $context);
    }

    public function preloadBranchStock(Collection $products, ?int $branchId = null): Collection
    {
        $branchId ??= $this->branchContext->currentBranchId();

        if (! $branchId) {
            return $products;
        }

        $products->load([
            'branchStocks' => fn ($query) => $query->where('branch_id', $branchId)->where('is_active', true),
        ]);

        return $products;
    }
}

<?php

namespace Modules\Catalog\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Security\Services\SecurityBranchContextService;

class ProductInventoryService
{
    public function __construct(
        private readonly SecurityBranchContextService $branchContext,
        private readonly InventoryMovementService $movements,
    ) {
    }

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
                'stock' => max(0, $stock),
                'min_stock' => max(0, $minStock),
                'is_active' => true,
            ]
        );

        $this->syncAggregateStock($product);
    }

    public function syncAggregateStock(Product $product): void
    {
        $warehouseTotals = ProductWarehouseStock::query()
            ->forCurrentOrganization()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock),0) as stock_total, COALESCE(SUM(min_stock),0) as min_stock_total')
            ->first();

        if ($warehouseTotals && ProductWarehouseStock::query()->forCurrentOrganization()->where('product_id', $product->id)->where('is_active', true)->exists()) {
            $product->forceFill([
                'stock' => (int) ($warehouseTotals->stock_total ?? 0),
                'min_stock' => (int) ($warehouseTotals->min_stock_total ?? 0),
            ])->save();

            return;
        }

        $totals = $product->branchStocks()
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

        $hasActiveWarehouses = ProductWarehouseStock::query()
            ->forCurrentOrganization()
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->exists();

        $branchStock->fill([
            'stock' => (int) ($totals?->stock_total ?? 0),
            'min_stock' => (int) ($totals?->min_stock_total ?? 0),
            'is_active' => $hasActiveWarehouses ? true : (bool) ($branchStock->is_active ?? false),
        ])->save();

        $this->syncAggregateStock($product->fresh());
    }

    public function availableStock(Product $product, ?int $branchId = null): int
    {
        $branchId ??= $this->branchContext->currentBranchId();

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

        if ($product->relationLoaded('branchStocks')) {
            $branchStock = $product->branchStocks
                ->first(fn ($stock) => (int) $stock->branch_id === $branchId && (bool) $stock->is_active);

            return (int) ($branchStock?->min_stock ?? 0);
        }

        return (int) ($product->branchStocks()->where('branch_id', $branchId)->where('is_active', true)->value('min_stock') ?? 0);
    }

    /**
     * @param array<int,int> $productIds
     * @return EloquentCollection<int,ProductBranchStock>
     */
    public function lockBranchStocksForProducts(array $productIds, int $branchId): EloquentCollection
    {
        return ProductBranchStock::query()
            ->forCurrentOrganization()
            ->whereIn('product_id', $productIds)
            ->where('branch_id', $branchId)
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

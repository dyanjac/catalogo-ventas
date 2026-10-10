<?php

namespace Modules\Catalog\Services;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\InventoryBalance;
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

    public function syncBranchStock(Product $product, ?int $branchId, int|float|string $stock, int|float|string $minStock): void
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
                'min_stock' => Decimal::nonNegative(Decimal::assertScale($minStock, 4)),
                'is_active' => true,
            ]
        );

        $this->movements->recordAdjustment($product, $branchId, Decimal::nonNegative(Decimal::assertScale($stock, 4)), [
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
            'stock' => Decimal::assertScale($totals?->stock_total ?? 0, 4),
            'min_stock' => Decimal::assertScale($totals?->min_stock_total ?? 0, 4),
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
            $unallocated = Decimal::sub($branchStock->stock ?? 0, $totals?->stock_total ?? 0, 4);
        }

        $hasActiveWarehouses = ProductWarehouseStock::query()
            ->forCurrentOrganization()
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->exists();

        $branchStock->fill([
            'stock' => Decimal::add($totals?->stock_total ?? 0, Decimal::nonNegative($unallocated), 4),
            'min_stock' => Decimal::assertScale($totals?->min_stock_total ?? 0, 4),
            'is_active' => $hasActiveWarehouses ? true : (bool) ($branchStock->is_active ?? false),
        ])->save();

        $this->syncAggregateStock($product->fresh());
    }

    public function availableStock(Product $product, ?int $branchId = null): int|string
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
            return $product->stock ?? 0;
        }

        if ($product->relationLoaded('branchStocks')) {
            $branchStock = $product->branchStocks
                ->first(fn ($stock) => (int) $stock->branch_id === $branchId && (bool) $stock->is_active);

            return $branchStock?->stock ?? 0;
        }

        return Decimal::quantity($product->branchStocks()->where('branch_id', $branchId)->where('is_active', true)->value('stock') ?? 0);
    }

    public function availableWarehouseStock(Product $product, int $branchId, int $warehouseId): int|string
    {
        $balance = InventoryBalance::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->first(['physical_stock', 'reserved_stock', 'is_active']);
        if ($balance) {
            return $balance->is_active ? $balance->availableStock() : 0;
        }
        if ($this->balanceReader->usesLedger((int) $product->organization_id)) {
            return $this->balanceReader->warehouseAvailableStock((int) $product->organization_id, (int) $product->id, $branchId, $warehouseId);
        }

        if ($product->relationLoaded('warehouseStocks')) {
            $warehouseStock = $product->warehouseStocks
                ->first(fn ($stock) => (int) $stock->branch_id === $branchId && (int) $stock->warehouse_id === $warehouseId && (bool) $stock->is_active);

            return $warehouseStock?->stock ?? 0;
        }

        return Decimal::quantity(ProductWarehouseStock::query()
            ->forCurrentOrganization()
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->value('stock') ?? 0);
    }

    public function minimumStock(Product $product, ?int $branchId = null): int|string
    {
        $branchId ??= $this->branchContext->currentBranchId();

        if (! $branchId) {
            return $product->min_stock ?? 0;
        }

        if ($this->balanceReader->usesLedger((int) $product->organization_id)) {
            return $this->balanceReader->branchMinimumStock((int) $product->organization_id, (int) $product->id, $branchId);
        }

        if ($product->relationLoaded('branchStocks')) {
            $branchStock = $product->branchStocks
                ->first(fn ($stock) => (int) $stock->branch_id === $branchId && (bool) $stock->is_active);

            return $branchStock?->min_stock ?? 0;
        }

        return Decimal::quantity($product->branchStocks()->where('branch_id', $branchId)->where('is_active', true)->value('min_stock') ?? 0);
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

    public function assertAvailable(Product $product, int|float|string $quantity, ?int $branchId = null): void
    {
        $branchId ??= $this->branchContext->currentBranchId();
        $available = $this->availableStock($product, $branchId);

        if (Decimal::compare($available, $quantity, 4) < 0) {
            throw new \Illuminate\Validation\ValidationException(
                validator: validator([], []),
                response: back()->withErrors([
                    'cart' => ["Stock insuficiente para {$product->name}. Disponible en la sucursal: {$available}."],
                ])
            );
        }
    }

    public function decrementBranchStock(Product $product, int $branchId, int|float|string $quantity, array $context = []): void
    {
        DB::transaction(function () use ($product, $branchId, $quantity, $context): void {
            $selectedWarehouseId = isset($context['warehouse_id']) ? (int) $context['warehouse_id'] : null;
            $warehouseStocks = ProductWarehouseStock::query()
                ->where('organization_id', $product->organization_id)
                ->where('product_id', $product->id)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->when($selectedWarehouseId !== null, fn ($query) => $query->where('warehouse_id', $selectedWarehouseId))
                ->with('warehouse')
                ->orderBy('warehouse_id')
                ->lockForUpdate()
                ->get();
            $balances = InventoryBalance::query()
                ->where('organization_id', $product->organization_id)
                ->where('product_id', $product->id)
                ->where('branch_id', $branchId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (InventoryBalance $balance): int => (int) ($balance->warehouse_id ?? 0));

            $remaining = Decimal::assertScale($quantity, 4);
            $allocations = [];
            foreach ($warehouseStocks->sortBy(fn (ProductWarehouseStock $stock): int => $stock->warehouse?->is_default ? 0 : 1) as $stock) {
                if ($selectedWarehouseId !== null && (int) $stock->warehouse_id !== $selectedWarehouseId) {
                    continue;
                }
                if (! $stock->warehouse?->is_active || (int) $stock->warehouse->branch_id !== $branchId) {
                    continue;
                }

                $balance = $balances->get((int) $stock->warehouse_id);
                $available = $balance
                    ? ($balance->is_active ? $balance->availableStock() : 0)
                    : $stock->stock;
                $available = Decimal::nonNegative($available);
                $allocated = Decimal::compare($remaining, $available, 4) < 0 ? $remaining : $available;
                if (Decimal::compare($allocated, 0, 4) > 0) {
                    $allocations[] = [(int) $stock->warehouse_id, $allocated];
                    $remaining = Decimal::sub($remaining, $allocated, 4);
                }
            }

            if (Decimal::compare($remaining, 0, 4) > 0 && $selectedWarehouseId === null) {
                $branchBalance = $balances->get(0);
                $branchStock = ProductBranchStock::query()
                    ->where('organization_id', $product->organization_id)
                    ->where('product_id', $product->id)
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();
                $available = $branchBalance
                    ? ($branchBalance->is_active ? $branchBalance->availableStock() : 0)
                    : ($branchStock?->is_active
                        ? Decimal::nonNegative(Decimal::sub($branchStock->stock, $warehouseStocks->sum('stock'), 4))
                        : 0);
                $available = Decimal::nonNegative($available);
                $allocated = Decimal::compare($remaining, $available, 4) < 0 ? $remaining : $available;
                if (Decimal::compare($allocated, 0, 4) > 0) {
                    $allocations[] = [null, $allocated];
                    $remaining = Decimal::sub($remaining, $allocated, 4);
                }
            }

            if (Decimal::compare($remaining, 0, 4) > 0) {
                throw ValidationException::withMessages([
                    'stock' => "Stock insuficiente para {$product->name} en el ".($selectedWarehouseId ? 'almacén seleccionado' : 'ámbito de la sucursal').'. Disponible: '.Decimal::sub($quantity, $remaining, 4).'.',
                ]);
            }

            foreach ($allocations as [$warehouseId, $allocated]) {
                $movementContext = $context;
                if ($warehouseId !== null) {
                    $movementContext['warehouse_id'] = $warehouseId;
                } else {
                    unset($movementContext['warehouse_id']);
                }
                if (isset($movementContext['idempotency_key'])) {
                    $movementContext['idempotency_key'] .= ':'.($warehouseId === null ? 'branch' : "warehouse-{$warehouseId}");
                }
                $this->movements->recordOutbound($product, $branchId, $allocated, $movementContext);
            }
        }, max(1, (int) config('catalog.reservations.transaction_attempts', 5)));
    }

    public function incrementBranchStock(Product $product, int $branchId, int|float|string $quantity, array $context = []): void
    {
        $this->movements->recordInbound($product, $branchId, $quantity, $context);
    }

    public function adjustBranchStock(Product $product, int $branchId, int|float|string $targetStock, array $context = []): void
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

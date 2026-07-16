<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\Organization;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Security\Models\SecurityBranch;

class InventoryLedgerBackfillService
{
    public function __construct(private readonly InventoryLedgerService $ledger) {}

    /** @return array{organizations:int,products:int,baselines:int,skipped:int,dry_run:bool} */
    public function run(?int $organizationId = null, int $chunk = 500, bool $dryRun = false): array
    {
        $stats = ['organizations' => 0, 'products' => 0, 'baselines' => 0, 'skipped' => 0, 'dry_run' => $dryRun];
        $organizations = Organization::query()
            ->when($organizationId, fn ($query) => $query->whereKey($organizationId))
            ->orderBy('id')
            ->get();

        foreach ($organizations as $organization) {
            $stats['organizations']++;
            Product::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->chunkById($chunk, function ($products) use ($dryRun, &$stats): void {
                    foreach ($products as $product) {
                        $stats['products']++;
                        $this->backfillProduct($product, $dryRun, $stats);
                    }
                });
        }

        return $stats;
    }

    /** @param array{organizations:int,products:int,baselines:int,skipped:int,dry_run:bool} $stats */
    private function backfillProduct(Product $product, bool $dryRun, array &$stats): void
    {
        $branchStocks = ProductBranchStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->orderBy('branch_id')
            ->get();
        $warehouseStocks = ProductWarehouseStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get();
        $activeWarehouseIds = InventoryWarehouse::query()
            ->where('organization_id', $product->organization_id)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($warehouseStocks as $stock) {
            $isActive = (bool) $stock->is_active && $activeWarehouseIds->contains((int) $stock->warehouse_id);
            $this->baseline(
                $product,
                (int) $stock->branch_id,
                (int) $stock->warehouse_id,
                (int) $stock->stock,
                (int) $stock->min_stock,
                (float) $stock->average_cost,
                $isActive,
                $dryRun,
                $stats,
            );
        }

        foreach ($branchStocks as $stock) {
            $activeWarehouseStocks = $warehouseStocks
                ->where('branch_id', $stock->branch_id)
                ->filter(fn (ProductWarehouseStock $warehouseStock): bool => (bool) $warehouseStock->is_active && $activeWarehouseIds->contains((int) $warehouseStock->warehouse_id));
            $warehouseTotal = (int) $activeWarehouseStocks->sum('stock');
            $warehouseMinimum = (int) $activeWarehouseStocks->sum('min_stock');
            $unallocated = max(0, (int) $stock->stock - $warehouseTotal);
            $unallocatedMinimum = max(0, (int) $stock->min_stock - $warehouseMinimum);
            $this->baseline(
                $product,
                (int) $stock->branch_id,
                null,
                $unallocated,
                $unallocatedMinimum,
                (float) ($product->average_price ?? $product->purchase_price ?? 0),
                (bool) $stock->is_active,
                $dryRun,
                $stats,
            );
        }

        if ($branchStocks->isNotEmpty()) {
            return;
        }

        $defaultBranchId = SecurityBranch::query()
            ->where('organization_id', $product->organization_id)
            ->where('is_default', true)
            ->value('id')
            ?? SecurityBranch::query()->where('organization_id', $product->organization_id)->orderBy('id')->value('id');

        if ($defaultBranchId) {
            $this->baseline(
                $product,
                (int) $defaultBranchId,
                null,
                (int) $product->stock,
                (int) $product->min_stock,
                (float) ($product->average_price ?? $product->purchase_price ?? 0),
                (bool) $product->is_active,
                $dryRun,
                $stats,
            );
        }
    }

    /** @param array{organizations:int,products:int,baselines:int,skipped:int,dry_run:bool} $stats */
    private function baseline(Product $product, int $branchId, ?int $warehouseId, int $stock, int $minStock, float $averageCost, bool $isActive, bool $dryRun, array &$stats): void
    {
        $locationKey = InventoryBalance::locationKey($branchId, $warehouseId);

        if (InventoryBalance::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('location_key', $locationKey)
            ->exists()) {
            $stats['skipped']++;

            return;
        }

        $stats['baselines']++;

        if ($dryRun) {
            return;
        }

        $this->ledger->initializeBaseline(
            (int) $product->organization_id,
            (int) $product->id,
            $branchId,
            $warehouseId,
            $stock,
            $minStock,
            $averageCost,
            'phase03:opening:'.$product->organization_id.':'.$product->id.':'.$locationKey,
            $isActive,
        );
    }
}

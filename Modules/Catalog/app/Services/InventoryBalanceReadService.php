<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;

class InventoryBalanceReadService
{
    public function usesLedger(int $organizationId): bool
    {
        $mode = InventoryLedgerRollout::query()
            ->where('organization_id', $organizationId)
            ->first()?->mode;

        return $mode === InventoryLedgerRolloutMode::Active;
    }

    public function branchStock(int $organizationId, int $productId, int $branchId): int
    {
        return (int) InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->sum('physical_stock');
    }

    public function warehouseStock(int $organizationId, int $productId, int $branchId, int $warehouseId): int
    {
        return (int) InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->value('physical_stock');
    }

    public function branchMinimumStock(int $organizationId, int $productId, int $branchId): int
    {
        return (int) InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->sum('min_stock');
    }
}

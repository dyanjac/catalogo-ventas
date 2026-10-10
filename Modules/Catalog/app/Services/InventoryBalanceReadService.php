<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Support\Decimal;
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

    public function branchStock(int $organizationId, int $productId, int $branchId): int|string
    {
        return Decimal::quantity(Decimal::round(InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->sum('physical_stock'), 4));
    }

    public function branchAvailableStock(int $organizationId, int $productId, int $branchId): int|string
    {
        return Decimal::quantity(Decimal::round(InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(physical_stock - reserved_stock), 0) as available_stock')
            ->value('available_stock'), 4));
    }

    public function productAvailableStock(int $organizationId, int $productId): int|string
    {
        return Decimal::quantity(Decimal::round(InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(physical_stock - reserved_stock), 0) as available_stock')
            ->value('available_stock'), 4));
    }

    public function warehouseStock(int $organizationId, int $productId, int $branchId, int $warehouseId): int|string
    {
        return Decimal::quantity(InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->value('physical_stock') ?? 0);
    }

    public function warehouseAvailableStock(int $organizationId, int $productId, int $branchId, int $warehouseId): int|string
    {
        $balance = InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->first(['physical_stock', 'reserved_stock']);

        return $balance?->availableStock() ?? 0;
    }

    public function warehouseInTransitStock(int $organizationId, int $productId, int $warehouseId): int|string
    {
        return Decimal::quantity(InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->value('in_transit_stock') ?? 0);
    }

    public function branchMinimumStock(int $organizationId, int $productId, int $branchId): int|string
    {
        return Decimal::quantity(Decimal::round(InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->sum('min_stock'), 4));
    }
}

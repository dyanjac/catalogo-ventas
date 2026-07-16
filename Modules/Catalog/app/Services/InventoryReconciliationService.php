<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryReconciliationIssue;
use Modules\Catalog\Entities\InventoryReconciliationRun;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;

class InventoryReconciliationService
{
    public function run(int $organizationId): InventoryReconciliationRun
    {
        return DB::transaction(function () use ($organizationId): InventoryReconciliationRun {
            $run = InventoryReconciliationRun::query()->create([
                'organization_id' => $organizationId,
                'status' => 'running',
                'started_at' => now(),
            ]);
            $checked = 0;

            InventoryBalance::query()
                ->where('organization_id', $organizationId)
                ->orderBy('id')
                ->chunkById(500, function ($balances) use ($run, &$checked): void {
                    foreach ($balances as $balance) {
                        $checked++;
                        $latest = InventoryMovement::query()
                            ->where('organization_id', $balance->organization_id)
                            ->where('inventory_balance_id', $balance->id)
                            ->where('ledger_generation', 1)
                            ->orderByDesc('balance_version')
                            ->first();

                        if (! $latest || (int) $latest->stock_after !== (int) $balance->physical_stock || (int) $latest->balance_version !== (int) $balance->version) {
                            $this->issue($run, $balance, 'ledger_balance_mismatch', $latest?->stock_after, $balance->physical_stock);
                        }

                        if ($balance->warehouse_id) {
                            $legacyWarehouse = ProductWarehouseStock::query()
                                ->where('organization_id', $balance->organization_id)
                                ->where('product_id', $balance->product_id)
                                ->where('branch_id', $balance->branch_id)
                                ->where('warehouse_id', $balance->warehouse_id)
                                ->first();

                            if (! $legacyWarehouse || (int) $legacyWarehouse->stock !== (int) $balance->physical_stock) {
                                $this->issue($run, $balance, 'warehouse_legacy_mismatch', $balance->physical_stock, $legacyWarehouse?->stock);
                            }

                            if ($legacyWarehouse && (bool) $legacyWarehouse->is_active !== (bool) $balance->is_active) {
                                $this->issue($run, $balance, 'warehouse_active_mismatch', $balance->is_active ? 1 : 0, $legacyWarehouse->is_active ? 1 : 0);
                            }
                        }
                    }
                });

            ProductWarehouseStock::query()
                ->where('organization_id', $organizationId)
                ->orderBy('id')
                ->chunkById(500, function ($stocks) use ($run): void {
                    foreach ($stocks as $stock) {
                        $exists = InventoryBalance::query()
                            ->where('organization_id', $stock->organization_id)
                            ->where('product_id', $stock->product_id)
                            ->where('branch_id', $stock->branch_id)
                            ->where('warehouse_id', $stock->warehouse_id)
                            ->exists();

                        if (! $exists) {
                            $this->issue($run, $stock, 'warehouse_balance_missing', $stock->stock, null);
                        }
                    }
                });

            $branchGroups = InventoryBalance::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->selectRaw('product_id, branch_id, SUM(physical_stock) as stock_total')
                ->groupBy('product_id', 'branch_id')
                ->get();

            foreach ($branchGroups as $group) {
                $legacy = ProductBranchStock::query()
                    ->where('organization_id', $organizationId)
                    ->where('product_id', $group->product_id)
                    ->where('branch_id', $group->branch_id)
                    ->value('stock');

                if ($legacy === null || (int) $legacy !== (int) $group->stock_total) {
                    $this->issue($run, $group, 'branch_legacy_mismatch', $group->stock_total, $legacy);
                }
            }

            $productGroups = InventoryBalance::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->selectRaw('product_id, SUM(physical_stock) as stock_total')
                ->groupBy('product_id')
                ->get();

            foreach ($productGroups as $group) {
                $legacy = Product::query()->where('organization_id', $organizationId)->whereKey($group->product_id)->value('stock');

                if ($legacy === null || (int) $legacy !== (int) $group->stock_total) {
                    $this->issue($run, $group, 'product_legacy_mismatch', $group->stock_total, $legacy);
                }
            }

            $issueCount = InventoryReconciliationIssue::query()->where('run_id', $run->id)->count();
            $run->forceFill([
                'status' => $issueCount === 0 ? 'passed' : 'failed',
                'checked_balances' => $checked,
                'issue_count' => $issueCount,
                'finished_at' => now(),
                'summary' => ['checked_balances' => $checked, 'issues' => $issueCount],
            ])->save();

            return $run->fresh('issues');
        });
    }

    private function issue(InventoryReconciliationRun $run, object $scope, string $type, mixed $expected, mixed $actual): void
    {
        InventoryReconciliationIssue::query()->create([
            'run_id' => $run->id,
            'organization_id' => $run->organization_id,
            'product_id' => $scope->product_id ?? null,
            'branch_id' => $scope->branch_id ?? null,
            'warehouse_id' => $scope->warehouse_id ?? null,
            'issue_type' => $type,
            'severity' => 'error',
            'expected_value' => $expected,
            'actual_value' => $actual,
        ]);
    }
}

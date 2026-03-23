<?php

namespace Modules\Catalog\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;

class InventoryMovementService
{
    public function recordInbound(Product $product, int $branchId, int $quantity, array $context = []): InventoryMovement
    {
        if (isset($context['warehouse_id'])) {
            return $this->recordWarehouseInbound($product, $branchId, (int) $context['warehouse_id'], $quantity, $context);
        }

        return $this->recordMovement($product, $branchId, abs($quantity), 'inbound', $context);
    }

    public function recordOutbound(Product $product, int $branchId, int $quantity, array $context = []): InventoryMovement
    {
        if (isset($context['warehouse_id'])) {
            return $this->recordWarehouseOutbound($product, $branchId, (int) $context['warehouse_id'], $quantity, $context);
        }

        return $this->recordMovement($product, $branchId, abs($quantity) * -1, 'outbound', $context);
    }

    public function recordAdjustment(Product $product, int $branchId, int $targetStock, array $context = []): InventoryMovement
    {
        $this->assertProductActiveForBranch($product, $branchId);

        $branchStock = ProductBranchStock::query()
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();

        $before = (int) ($branchStock?->stock ?? 0);
        $after = max(0, $targetStock);
        $delta = $after - $before;

        ProductBranchStock::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'branch_id' => $branchId,
            ],
            [
                'stock' => $after,
                'min_stock' => (int) ($branchStock?->min_stock ?? $product->min_stock ?? 0),
                'is_active' => true,
            ]
        );

        app(ProductInventoryService::class)->syncAggregateStock($product->fresh());

        return InventoryMovement::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branchId,
            'movement_type' => 'adjustment',
            'reason' => $context['reason'] ?? 'manual_adjustment',
            'quantity' => $delta,
            'stock_before' => $before,
            'stock_after' => $after,
            'average_cost_before' => 0,
            'unit_cost' => 0,
            'average_cost_after' => 0,
            'total_cost' => 0,
            'performed_by' => $this->actorId($context['performed_by'] ?? auth()->user()),
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'reference_code' => $context['reference_code'] ?? null,
            'notes' => $context['notes'] ?? null,
            'meta' => $context['meta'] ?? null,
        ]);
    }

    protected function recordMovement(Product $product, int $branchId, int $quantityDelta, string $movementType, array $context = []): InventoryMovement
    {
        return DB::transaction(function () use ($product, $branchId, $quantityDelta, $movementType, $context): InventoryMovement {
            $this->assertProductActiveForBranch($product, $branchId);

            $branchStock = ProductBranchStock::query()
                ->where('product_id', $product->id)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            $before = (int) ($branchStock?->stock ?? 0);
            $after = $before + $quantityDelta;

            if ($after < 0) {
                throw new \RuntimeException("Stock insuficiente en sucursal para {$product->name}.");
            }

            ProductBranchStock::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                ],
                [
                    'stock' => $after,
                    'min_stock' => (int) ($branchStock?->min_stock ?? $product->min_stock ?? 0),
                    'is_active' => true,
                ]
            );

            app(ProductInventoryService::class)->syncAggregateStock($product->fresh());

            return InventoryMovement::query()->create([
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'movement_type' => $movementType,
                'reason' => $context['reason'] ?? null,
                'quantity' => $quantityDelta,
                'stock_before' => $before,
                'stock_after' => $after,
                'average_cost_before' => 0,
                'unit_cost' => 0,
                'average_cost_after' => 0,
                'total_cost' => 0,
                'performed_by' => $this->actorId($context['performed_by'] ?? auth()->user()),
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'reference_code' => $context['reference_code'] ?? null,
                'notes' => $context['notes'] ?? null,
                'meta' => $context['meta'] ?? null,
            ]);
        });
    }

    public function recordWarehouseInbound(Product $product, int $branchId, int $warehouseId, int $quantity, array $context = []): InventoryMovement
    {
        return DB::transaction(function () use ($product, $branchId, $warehouseId, $quantity, $context): InventoryMovement {
            $this->assertProductActiveForWarehouse($product, $branchId, $warehouseId);
            $stock = $this->lockWarehouseStock($product->id, $branchId, $warehouseId);

            $before = (int) ($stock?->stock ?? 0);
            $after = $before + abs($quantity);
            $averageCostBefore = round((float) ($stock?->average_cost ?? 0), 4);
            $unitCost = round((float) ($context['unit_cost'] ?? $product->average_price ?? $product->purchase_price ?? 0), 4);
            $averageCostAfter = $after > 0
                ? round((($before * $averageCostBefore) + (abs($quantity) * $unitCost)) / $after, 4)
                : 0.0;

            ProductWarehouseStock::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'stock' => $after,
                    'min_stock' => (int) ($stock?->min_stock ?? $product->min_stock ?? 0),
                    'average_cost' => $averageCostAfter,
                    'last_cost' => $unitCost,
                    'is_active' => true,
                ]
            );

            app(ProductInventoryService::class)->syncBranchAggregateStock($product->fresh(), $branchId);

            return InventoryMovement::query()->create([
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'movement_type' => 'inbound',
                'reason' => $context['reason'] ?? null,
                'quantity' => abs($quantity),
                'stock_before' => $before,
                'stock_after' => $after,
                'average_cost_before' => $averageCostBefore,
                'unit_cost' => $unitCost,
                'average_cost_after' => $averageCostAfter,
                'total_cost' => round(abs($quantity) * $unitCost, 4),
                'performed_by' => $this->actorId($context['performed_by'] ?? auth()->user()),
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'reference_code' => $context['reference_code'] ?? null,
                'notes' => $context['notes'] ?? null,
                'meta' => $context['meta'] ?? null,
            ]);
        });
    }

    public function recordWarehouseOutbound(Product $product, int $branchId, int $warehouseId, int $quantity, array $context = []): InventoryMovement
    {
        return DB::transaction(function () use ($product, $branchId, $warehouseId, $quantity, $context): InventoryMovement {
            $this->assertProductActiveForWarehouse($product, $branchId, $warehouseId);
            $stock = $this->lockWarehouseStock($product->id, $branchId, $warehouseId);

            $before = (int) ($stock?->stock ?? 0);
            $delta = abs($quantity);
            $after = $before - $delta;

            if ($after < 0) {
                throw new \RuntimeException("Stock insuficiente en almacen para {$product->name}.");
            }

            $averageCostBefore = round((float) ($stock?->average_cost ?? 0), 4);
            $unitCost = round((float) ($context['unit_cost'] ?? $averageCostBefore), 4);
            $averageCostAfter = $after > 0 ? $averageCostBefore : 0.0;

            ProductWarehouseStock::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'stock' => $after,
                    'min_stock' => (int) ($stock?->min_stock ?? $product->min_stock ?? 0),
                    'average_cost' => $averageCostAfter,
                    'last_cost' => $unitCost,
                    'is_active' => true,
                ]
            );

            app(ProductInventoryService::class)->syncBranchAggregateStock($product->fresh(), $branchId);

            return InventoryMovement::query()->create([
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'movement_type' => 'outbound',
                'reason' => $context['reason'] ?? null,
                'quantity' => $delta * -1,
                'stock_before' => $before,
                'stock_after' => $after,
                'average_cost_before' => $averageCostBefore,
                'unit_cost' => $unitCost,
                'average_cost_after' => $averageCostAfter,
                'total_cost' => round($delta * $unitCost, 4),
                'performed_by' => $this->actorId($context['performed_by'] ?? auth()->user()),
                'reference_type' => $context['reference_type'] ?? null,
                'reference_id' => $context['reference_id'] ?? null,
                'reference_code' => $context['reference_code'] ?? null,
                'notes' => $context['notes'] ?? null,
                'meta' => $context['meta'] ?? null,
            ]);
        });
    }

    protected function actorId(mixed $actor): ?int
    {
        if ($actor instanceof User) {
            return $actor->id;
        }

        return is_numeric($actor) ? (int) $actor : null;
    }

    protected function lockWarehouseStock(int $productId, int $branchId, int $warehouseId): ?ProductWarehouseStock
    {
        return ProductWarehouseStock::query()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
    }

    private function assertProductActiveForBranch(Product $product, int $branchId): void
    {
        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product' => "El producto {$product->name} esta inactivo a nivel global.",
            ]);
        }

        $branchStock = ProductBranchStock::query()
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->first();

        if (! $branchStock || ! $branchStock->is_active) {
            throw ValidationException::withMessages([
                'branch' => "El producto {$product->name} no esta habilitado para la sucursal seleccionada.",
            ]);
        }
    }

    private function assertProductActiveForWarehouse(Product $product, int $branchId, int $warehouseId): void
    {
        $this->assertProductActiveForBranch($product, $branchId);

        $warehouse = InventoryWarehouse::query()->whereKey($warehouseId)->where('branch_id', $branchId)->first();

        if (! $warehouse || ! $warehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse' => 'El almacen seleccionado no esta activo para registrar movimientos.',
            ]);
        }

        $warehouseStock = ProductWarehouseStock::query()
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (! $warehouseStock || ! $warehouseStock->is_active) {
            throw ValidationException::withMessages([
                'warehouse' => "El producto {$product->name} no esta habilitado para el almacen seleccionado.",
            ]);
        }
    }
}

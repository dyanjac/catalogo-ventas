<?php

namespace Modules\Catalog\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;

class InventoryMovementService
{
    public function recordInbound(Product $product, int $branchId, int $quantity, array $context = []): InventoryMovement
    {
        return $this->recordMovement($product, $branchId, abs($quantity), 'inbound', $context);
    }

    public function recordOutbound(Product $product, int $branchId, int $quantity, array $context = []): InventoryMovement
    {
        return $this->recordMovement($product, $branchId, abs($quantity) * -1, 'outbound', $context);
    }

    public function recordAdjustment(Product $product, int $branchId, int $targetStock, array $context = []): InventoryMovement
    {
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
}

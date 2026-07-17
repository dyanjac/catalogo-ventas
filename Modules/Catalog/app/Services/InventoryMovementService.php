<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Data\InventoryMovementCommand;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\InventoryMovementReason;
use Modules\Catalog\Enums\InventoryMovementType;

class InventoryMovementService
{
    public function __construct(private readonly InventoryLedgerService $ledger) {}

    public function recordInbound(Product $product, int $branchId, int $quantity, array $context = []): InventoryMovement
    {
        if (isset($context['warehouse_id'])) {
            return $this->recordWarehouseInbound($product, $branchId, (int) $context['warehouse_id'], $quantity, $context);
        }

        return $this->recordBranchMovement($product, $branchId, abs($quantity), InventoryMovementType::Inbound, $context);
    }

    public function recordOutbound(Product $product, int $branchId, int $quantity, array $context = []): InventoryMovement
    {
        if (isset($context['warehouse_id'])) {
            return $this->recordWarehouseOutbound($product, $branchId, (int) $context['warehouse_id'], $quantity, $context);
        }

        return $this->recordBranchMovement($product, $branchId, abs($quantity) * -1, InventoryMovementType::Outbound, $context);
    }

    public function recordAdjustment(Product $product, int $branchId, int $targetStock, array $context = []): InventoryMovement
    {
        return DB::transaction(function () use ($product, $branchId, $targetStock, $context): InventoryMovement {
            $stock = $this->assertProductActiveForBranch($product, $branchId);
            $warehouseStock = $this->warehouseStockTotal($product, $branchId);
            $movement = $this->ledger->append($this->command(
                product: $product,
                branchId: $branchId,
                warehouseId: null,
                type: InventoryMovementType::Adjustment,
                context: $context,
                targetStock: max(0, $targetStock - $warehouseStock),
                initialStock: max(0, (int) $stock->stock - $warehouseStock),
            ));

            $this->mirrorBranchMovement($product, $stock, $movement);

            return $movement;
        }, 5);
    }

    public function recordWarehouseInbound(Product $product, int $branchId, int $warehouseId, int $quantity, array $context = []): InventoryMovement
    {
        return $this->recordWarehouseMovement($product, $branchId, $warehouseId, abs($quantity), InventoryMovementType::Inbound, $context);
    }

    public function recordWarehouseOutbound(Product $product, int $branchId, int $warehouseId, int $quantity, array $context = []): InventoryMovement
    {
        return $this->recordWarehouseMovement($product, $branchId, $warehouseId, abs($quantity) * -1, InventoryMovementType::Outbound, $context);
    }

    public function recordReservedOutbound(InventoryBalance $balance, int $quantity, array $context = []): InventoryMovement
    {
        $product = Product::query()
            ->where('organization_id', $balance->organization_id)
            ->findOrFail($balance->product_id);
        $context['reserved_stock_delta'] = abs($quantity) * -1;

        return $balance->warehouse_id
            ? $this->recordWarehouseOutbound($product, (int) $balance->branch_id, (int) $balance->warehouse_id, $quantity, $context)
            : $this->recordOutbound($product, (int) $balance->branch_id, $quantity, $context);
    }

    public function recordTransitInbound(InventoryBalance $balance, int $quantity, array $context = []): InventoryMovement
    {
        $product = Product::query()
            ->where('organization_id', $balance->organization_id)
            ->findOrFail($balance->product_id);
        $context['in_transit_stock_delta'] = abs($quantity) * -1;

        return $balance->warehouse_id
            ? $this->recordWarehouseInbound($product, (int) $balance->branch_id, (int) $balance->warehouse_id, $quantity, $context)
            : $this->recordInbound($product, (int) $balance->branch_id, $quantity, $context);
    }

    public function recordWarehouseOpeningStock(Product $product, int $branchId, int $warehouseId, int $stock, array $context = []): InventoryMovement
    {
        return DB::transaction(function () use ($product, $branchId, $warehouseId, $stock, $context): InventoryMovement {
            $legacy = $this->assertProductActiveForWarehouse($product, $branchId, $warehouseId);
            $movement = $this->ledger->append($this->command(
                product: $product,
                branchId: $branchId,
                warehouseId: $warehouseId,
                type: InventoryMovementType::OpeningStock,
                context: $context,
                quantityDelta: max(0, $stock),
                initialStock: 0,
                initialAverageCost: 0,
                requireEmptyLedger: true,
            ));

            $this->mirrorWarehouseMovement($product, $legacy, $movement);

            return $movement;
        }, 5);
    }

    public function recordWarehouseAdjustment(Product $product, int $branchId, int $warehouseId, int $targetStock, array $context = []): InventoryMovement
    {
        return DB::transaction(function () use ($product, $branchId, $warehouseId, $targetStock, $context): InventoryMovement {
            $legacy = $this->assertProductActiveForWarehouse($product, $branchId, $warehouseId);
            $movement = $this->ledger->append($this->command(
                product: $product,
                branchId: $branchId,
                warehouseId: $warehouseId,
                type: InventoryMovementType::Adjustment,
                context: $context,
                targetStock: max(0, $targetStock),
                initialStock: (int) $legacy->stock,
                initialAverageCost: (float) $legacy->average_cost,
            ));

            $this->mirrorWarehouseMovement($product, $legacy, $movement);

            return $movement;
        }, 5);
    }

    public function reverse(InventoryMovement $movement, ?int $actorId = null, ?string $reason = null, ?string $idempotencyKey = null): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $actorId, $reason, $idempotencyKey): InventoryMovement {
            $reversal = $this->ledger->reverse(
                $movement,
                $idempotencyKey ?? 'inventory-movement:'.$movement->id.':reversal',
                $actorId,
                $reason,
            );
            $product = Product::query()->where('organization_id', $movement->organization_id)->findOrFail($movement->product_id);

            if ($movement->warehouse_id) {
                $legacy = ProductWarehouseStock::query()
                    ->where('organization_id', $movement->organization_id)
                    ->where('product_id', $movement->product_id)
                    ->where('branch_id', $movement->branch_id)
                    ->where('warehouse_id', $movement->warehouse_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->mirrorWarehouseMovement($product, $legacy, $reversal);
            } else {
                $legacy = ProductBranchStock::query()
                    ->where('organization_id', $movement->organization_id)
                    ->where('product_id', $movement->product_id)
                    ->where('branch_id', $movement->branch_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $this->mirrorBranchMovement($product, $legacy, $reversal);
            }

            return $reversal;
        }, 5);
    }

    private function recordBranchMovement(Product $product, int $branchId, int $quantityDelta, InventoryMovementType $type, array $context): InventoryMovement
    {
        return DB::transaction(function () use ($product, $branchId, $quantityDelta, $type, $context): InventoryMovement {
            $stock = $this->assertProductActiveForBranch($product, $branchId);
            $warehouseStock = $this->warehouseStockTotal($product, $branchId);
            $movement = $this->ledger->append($this->command(
                product: $product,
                branchId: $branchId,
                warehouseId: null,
                type: $type,
                context: $context,
                quantityDelta: $quantityDelta,
                initialStock: max(0, (int) $stock->stock - $warehouseStock),
            ));
            $this->mirrorBranchMovement($product, $stock, $movement);

            return $movement;
        }, 5);
    }

    private function recordWarehouseMovement(Product $product, int $branchId, int $warehouseId, int $quantityDelta, InventoryMovementType $type, array $context): InventoryMovement
    {
        return DB::transaction(function () use ($product, $branchId, $warehouseId, $quantityDelta, $type, $context): InventoryMovement {
            $stock = $this->assertProductActiveForWarehouse($product, $branchId, $warehouseId);
            $movement = $this->ledger->append($this->command(
                product: $product,
                branchId: $branchId,
                warehouseId: $warehouseId,
                type: $type,
                context: $context,
                quantityDelta: $quantityDelta,
                initialStock: (int) $stock->stock,
                initialAverageCost: (float) $stock->average_cost,
            ));
            $this->mirrorWarehouseMovement($product, $stock, $movement);

            return $movement;
        }, 5);
    }

    private function command(
        Product $product,
        int $branchId,
        ?int $warehouseId,
        InventoryMovementType $type,
        array $context,
        ?int $quantityDelta = null,
        ?int $targetStock = null,
        int $initialStock = 0,
        float $initialAverageCost = 0,
        bool $requireEmptyLedger = false,
    ): InventoryMovementCommand {
        $referenceType = $context['reference_type'] ?? null;
        $referenceId = isset($context['reference_id']) ? (int) $context['reference_id'] : null;
        $key = $context['idempotency_key'] ?? null;

        if (! $key && $referenceType && $referenceId) {
            $key = implode(':', [
                'inventory', sha1((string) $referenceType), $referenceId,
                $product->id, $warehouseId ? 'warehouse-'.$warehouseId : 'branch-'.$branchId, $type->value,
            ]);
        }

        return new InventoryMovementCommand(
            organizationId: (int) $product->organization_id,
            productId: (int) $product->id,
            branchId: $branchId,
            warehouseId: $warehouseId,
            type: $type,
            reasonCode: $this->reasonCode($context, $type),
            idempotencyKey: (string) ($key ?: Str::uuid()),
            quantityDelta: $quantityDelta,
            targetStock: $targetStock,
            initialStock: $initialStock,
            initialAverageCost: $initialAverageCost,
            unitCost: round((float) ($context['unit_cost'] ?? $initialAverageCost), 4),
            performedBy: $this->actorId($context['performed_by'] ?? auth()->user()),
            reason: $context['reason'] ?? null,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceCode: $context['reference_code'] ?? null,
            notes: $context['notes'] ?? null,
            meta: $context['meta'] ?? null,
            requireEmptyLedger: $requireEmptyLedger,
            reservedStockDelta: (int) ($context['reserved_stock_delta'] ?? 0),
            inTransitStockDelta: (int) ($context['in_transit_stock_delta'] ?? 0),
        );
    }

    private function reasonCode(array $context, InventoryMovementType $type): InventoryMovementReason
    {
        $candidate = $context['reason_code'] ?? $context['reason'] ?? null;

        if (is_string($candidate) && InventoryMovementReason::tryFrom($candidate)) {
            return InventoryMovementReason::from($candidate);
        }

        return match ($type) {
            InventoryMovementType::OpeningStock => InventoryMovementReason::InitialStock,
            InventoryMovementType::Adjustment => InventoryMovementReason::ManualAdjustment,
            InventoryMovementType::Reversal => InventoryMovementReason::Reversal,
            default => InventoryMovementReason::Other,
        };
    }

    private function mirrorBranchMovement(Product $product, ProductBranchStock $stock, InventoryMovement $movement): void
    {
        $stock->forceFill([
            'stock' => $this->warehouseStockTotal($product, (int) $movement->branch_id) + (int) $movement->stock_after,
            'is_active' => true,
        ])->save();

        $this->syncProductAggregate($product);
    }

    private function mirrorWarehouseMovement(Product $product, ProductWarehouseStock $stock, InventoryMovement $movement): void
    {
        $stock->forceFill([
            'stock' => $movement->stock_after,
            'average_cost' => $movement->average_cost_after,
            'last_cost' => $movement->unit_cost,
            'is_active' => true,
        ])->save();

        $warehouseTotals = ProductWarehouseStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $movement->branch_id)
            ->where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock),0) as stock_total, COALESCE(SUM(min_stock),0) as min_stock_total')
            ->first();

        $unallocated = \Modules\Catalog\Entities\InventoryBalance::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $movement->branch_id)
            ->whereNull('warehouse_id')
            ->value('physical_stock');

        if ($unallocated === null) {
            $legacyBranchStock = ProductBranchStock::query()
                ->where('organization_id', $product->organization_id)
                ->where('product_id', $product->id)
                ->where('branch_id', $movement->branch_id)
                ->value('stock') ?? 0;
            $previousWarehouseTotal = (int) ($warehouseTotals?->stock_total ?? 0) - (int) $movement->stock_after + (int) $movement->stock_before;
            $unallocated = max(0, (int) $legacyBranchStock - $previousWarehouseTotal);
        }

        ProductBranchStock::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'branch_id' => $movement->branch_id,
            ],
            [
                'organization_id' => $product->organization_id,
                'stock' => (int) ($warehouseTotals?->stock_total ?? 0) + (int) $unallocated,
                'min_stock' => (int) ($warehouseTotals?->min_stock_total ?? 0),
                'is_active' => true,
            ]
        );

        $this->syncProductAggregate($product);
    }

    private function syncProductAggregate(Product $product): void
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

    private function assertProductActiveForBranch(Product $product, int $branchId): ProductBranchStock
    {
        if (! $product->is_active) {
            throw ValidationException::withMessages(['product' => "El producto {$product->name} esta inactivo a nivel global."]);
        }

        if (! $product->tracksInventory()) {
            throw ValidationException::withMessages(['product' => "El producto {$product->name} no controla inventario fisico."]);
        }

        $branchStock = ProductBranchStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();

        if (! $branchStock || ! $branchStock->is_active) {
            throw ValidationException::withMessages(['branch' => "El producto {$product->name} no esta habilitado para la sucursal seleccionada."]);
        }

        return $branchStock;
    }

    private function assertProductActiveForWarehouse(Product $product, int $branchId, int $warehouseId): ProductWarehouseStock
    {
        $this->assertProductActiveForBranch($product, $branchId);
        $warehouse = InventoryWarehouse::query()
            ->where('organization_id', $product->organization_id)
            ->whereKey($warehouseId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();
        $stock = ProductWarehouseStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $warehouse || ! $stock || ! $stock->is_active) {
            throw ValidationException::withMessages(['warehouse' => "El producto {$product->name} no esta habilitado para el almacen seleccionado."]);
        }

        return $stock;
    }

    private function actorId(mixed $actor): ?int
    {
        if ($actor instanceof User) {
            return $actor->id;
        }

        return is_numeric($actor) ? (int) $actor : null;
    }

    private function warehouseStockTotal(Product $product, int $branchId): int
    {
        return (int) ProductWarehouseStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->sum('stock');
    }
}

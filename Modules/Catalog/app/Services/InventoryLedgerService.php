<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Data\InventoryMovementCommand;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Enums\InventoryLocationType;
use Modules\Catalog\Enums\InventoryMovementReason;
use Modules\Catalog\Enums\InventoryMovementType;
use RuntimeException;

class InventoryLedgerService
{
    public function append(InventoryMovementCommand $command): InventoryMovement
    {
        $this->assertScope($command);
        $payloadHash = $this->payloadHash($command);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return DB::transaction(function () use ($command, $payloadHash): InventoryMovement {
                    $existing = InventoryMovement::query()
                        ->where('organization_id', $command->organizationId)
                        ->where('idempotency_key', $command->idempotencyKey)
                        ->first();

                    if ($existing) {
                        return $this->validateReplay($existing, $payloadHash);
                    }

                    $balance = $this->lockOrCreateBalance($command);

                    // Current read: otra transaccion pudo confirmar la misma clave mientras esperabamos el saldo.
                    $existing = InventoryMovement::query()
                        ->where('organization_id', $command->organizationId)
                        ->where('idempotency_key', $command->idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return $this->validateReplay($existing, $payloadHash);
                    }

                    if ($command->requireEmptyLedger && ! $this->canReceiveOpeningStock($balance)) {
                        throw ValidationException::withMessages([
                            'stock' => 'El stock inicial solo puede registrarse en una ubicacion sin movimientos previos.',
                        ]);
                    }

                    $before = (int) $balance->physical_stock;
                    $quantityDelta = $command->targetStock !== null
                        ? $command->targetStock - $before
                        : (int) $command->quantityDelta;
                    $after = $before + $quantityDelta;
                    $reservedAfter = (int) $balance->reserved_stock + $command->reservedStockDelta;
                    $inTransitAfter = (int) $balance->in_transit_stock + $command->inTransitStockDelta;

                    if ($reservedAfter < 0 || $inTransitAfter < 0 || $after < $reservedAfter) {
                        throw ValidationException::withMessages([
                            'stock' => 'El movimiento viola los saldos fisico, reservado o en transito.',
                        ]);
                    }

                    $averageBefore = round((float) $balance->average_cost, 4);
                    $unitCost = round($command->unitCost > 0 ? $command->unitCost : $averageBefore, 4);
                    $averageAfter = $this->averageCostAfter($before, $after, $quantityDelta, $averageBefore, $unitCost, $command->type);
                    $nextVersion = (int) $balance->version + 1;

                    $movement = InventoryMovement::query()->create([
                        'organization_id' => $command->organizationId,
                        'inventory_balance_id' => $balance->id,
                        'product_id' => $command->productId,
                        'branch_id' => $command->branchId,
                        'warehouse_id' => $command->warehouseId,
                        'movement_type' => $command->type->value,
                        'idempotency_key' => $command->idempotencyKey,
                        'payload_hash' => $payloadHash,
                        'reason' => $command->reason,
                        'reason_code' => $command->reasonCode->value,
                        'quantity' => $quantityDelta,
                        'stock_before' => $before,
                        'stock_after' => $after,
                        'balance_version' => $nextVersion,
                        'average_cost_before' => $averageBefore,
                        'unit_cost' => $unitCost,
                        'average_cost_after' => $averageAfter,
                        'total_cost' => round(abs($quantityDelta) * $unitCost, 4),
                        'performed_by' => $command->performedBy,
                        'reference_type' => $command->referenceType,
                        'reference_id' => $command->referenceId,
                        'reference_code' => $command->referenceCode,
                        'reversal_of_id' => $command->reversalOfId,
                        'ledger_generation' => 1,
                        'occurred_at' => now(),
                        'notes' => $command->notes,
                        'meta' => $command->meta,
                    ]);

                    $balance->forceFill([
                        'physical_stock' => $after,
                        'average_cost' => $averageAfter,
                        'last_cost' => $unitCost,
                        'version' => $nextVersion,
                        'reserved_stock' => $reservedAfter,
                        'in_transit_stock' => $inTransitAfter,
                        'reservation_version' => (int) $balance->reservation_version + ($command->reservedStockDelta !== 0 ? 1 : 0),
                        'transit_version' => (int) $balance->transit_version + ($command->inTransitStockDelta !== 0 ? 1 : 0),
                    ])->save();

                    return $movement;
                }, max(1, (int) config('catalog.reservations.transaction_attempts', 5)));
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                $existing = InventoryMovement::query()
                    ->where('organization_id', $command->organizationId)
                    ->where('idempotency_key', $command->idempotencyKey)
                    ->first();

                if ($existing) {
                    return $this->validateReplay($existing, $payloadHash);
                }

                if ($attempt === 1) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('No se pudo registrar el movimiento de inventario.');
    }

    public function initializeBaseline(
        int $organizationId,
        int $productId,
        int $branchId,
        ?int $warehouseId,
        int $stock,
        int $minStock,
        float $averageCost,
        string $idempotencyKey,
        bool $isActive = true,
    ): InventoryMovement {
        $movement = $this->append(new InventoryMovementCommand(
            organizationId: $organizationId,
            productId: $productId,
            branchId: $branchId,
            warehouseId: $warehouseId,
            type: InventoryMovementType::OpeningStock,
            reasonCode: InventoryMovementReason::LegacyBaseline,
            idempotencyKey: $idempotencyKey,
            quantityDelta: $stock,
            initialStock: 0,
            initialAverageCost: $averageCost,
            unitCost: $averageCost,
            reason: 'phase03_legacy_baseline',
            meta: ['min_stock' => $minStock, 'source' => 'phase03_backfill'],
            requireEmptyLedger: true,
        ));

        InventoryBalance::query()->whereKey($movement->inventory_balance_id)->update([
            'min_stock' => max(0, $minStock),
            'is_active' => $isActive,
        ]);

        return $movement;
    }

    public function reverse(InventoryMovement $movement, string $idempotencyKey, ?int $actorId = null, ?string $reason = null): InventoryMovement
    {
        if ((int) $movement->ledger_generation !== 1 || ! $movement->inventory_balance_id) {
            throw ValidationException::withMessages([
                'movement' => 'Solo se pueden revertir movimientos emitidos por el ledger vigente.',
            ]);
        }

        return $this->append(new InventoryMovementCommand(
            organizationId: (int) $movement->organization_id,
            productId: (int) $movement->product_id,
            branchId: (int) $movement->branch_id,
            warehouseId: $movement->warehouse_id ? (int) $movement->warehouse_id : null,
            type: InventoryMovementType::Reversal,
            reasonCode: InventoryMovementReason::Reversal,
            idempotencyKey: $idempotencyKey,
            quantityDelta: ((int) $movement->quantity) * -1,
            initialStock: (int) $movement->stock_after,
            initialAverageCost: (float) $movement->average_cost_after,
            unitCost: (float) $movement->unit_cost,
            performedBy: $actorId,
            reason: $reason ?? 'movement_reversal',
            referenceType: InventoryMovement::class,
            referenceId: (int) $movement->id,
            referenceCode: $movement->reference_code,
            reversalOfId: (int) $movement->id,
            meta: ['reversed_idempotency_key' => $movement->idempotency_key],
        ));
    }

    private function lockOrCreateBalance(InventoryMovementCommand $command): InventoryBalance
    {
        $locationKey = InventoryBalance::locationKey($command->branchId, $command->warehouseId);
        $balance = InventoryBalance::query()->firstOrCreate(
            [
                'organization_id' => $command->organizationId,
                'product_id' => $command->productId,
                'location_key' => $locationKey,
            ],
            [
                'branch_id' => $command->branchId,
                'warehouse_id' => $command->warehouseId,
                'location_type' => $command->warehouseId ? InventoryLocationType::Warehouse->value : InventoryLocationType::Unallocated->value,
                'physical_stock' => 0,
                'reserved_stock' => 0,
                'in_transit_stock' => 0,
                'average_cost' => $command->initialAverageCost,
                'last_cost' => $command->initialAverageCost,
                'version' => 0,
                'is_active' => true,
            ]
        );

        $balance = InventoryBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();

        if ($balance->version === 0 && $command->initialStock !== 0 && $command->type !== InventoryMovementType::OpeningStock) {
            $this->createRuntimeBaseline($balance, $command);
            $balance->refresh();
        }

        return $balance;
    }

    private function createRuntimeBaseline(InventoryBalance $balance, InventoryMovementCommand $command): void
    {
        $key = 'phase03:runtime-opening:'.$command->organizationId.':'.$command->productId.':'.$balance->location_key;
        $hash = hash('sha256', json_encode(['key' => $key, 'stock' => $command->initialStock, 'cost' => $command->initialAverageCost], JSON_THROW_ON_ERROR));

        InventoryMovement::query()->create([
            'organization_id' => $command->organizationId,
            'inventory_balance_id' => $balance->id,
            'product_id' => $command->productId,
            'branch_id' => $command->branchId,
            'warehouse_id' => $command->warehouseId,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'idempotency_key' => $key,
            'payload_hash' => $hash,
            'reason' => 'runtime_legacy_baseline',
            'reason_code' => InventoryMovementReason::LegacyBaseline->value,
            'quantity' => $command->initialStock,
            'stock_before' => 0,
            'stock_after' => $command->initialStock,
            'balance_version' => 1,
            'average_cost_before' => 0,
            'unit_cost' => $command->initialAverageCost,
            'average_cost_after' => $command->initialAverageCost,
            'total_cost' => round(abs($command->initialStock) * $command->initialAverageCost, 4),
            'ledger_generation' => 1,
            'occurred_at' => now(),
            'meta' => ['source' => 'runtime_legacy_mirror'],
        ]);

        $balance->forceFill([
            'physical_stock' => $command->initialStock,
            'average_cost' => $command->initialAverageCost,
            'last_cost' => $command->initialAverageCost,
            'version' => 1,
        ])->save();
    }

    private function averageCostAfter(
        int $before,
        int $after,
        int $delta,
        float $averageBefore,
        float $unitCost,
        InventoryMovementType $type,
    ): float {
        if ($after === 0) {
            return 0.0;
        }

        if ($delta > 0 && in_array($type, [InventoryMovementType::Inbound, InventoryMovementType::OpeningStock], true)) {
            return round((($before * $averageBefore) + ($delta * $unitCost)) / $after, 4);
        }

        return $averageBefore;
    }

    private function canReceiveOpeningStock(InventoryBalance $balance): bool
    {
        if ($balance->version === 0 && $balance->physical_stock === 0) {
            return true;
        }

        if ($balance->version !== 1 || $balance->physical_stock !== 0) {
            return false;
        }

        return InventoryMovement::query()
            ->where('inventory_balance_id', $balance->id)
            ->where('balance_version', 1)
            ->where('movement_type', InventoryMovementType::OpeningStock->value)
            ->where('reason_code', InventoryMovementReason::LegacyBaseline->value)
            ->where('quantity', 0)
            ->exists();
    }

    private function validateReplay(InventoryMovement $movement, string $payloadHash): InventoryMovement
    {
        if (! hash_equals((string) $movement->payload_hash, $payloadHash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave idempotente ya fue usada con un contenido diferente.',
            ]);
        }

        return $movement;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }

    private function payloadHash(InventoryMovementCommand $command): string
    {
        $payload = [
            'organization_id' => $command->organizationId,
            'product_id' => $command->productId,
            'branch_id' => $command->branchId,
            'warehouse_id' => $command->warehouseId,
            'type' => $command->type->value,
            'reason_code' => $command->reasonCode->value,
            'quantity_delta' => $command->quantityDelta,
            'target_stock' => $command->targetStock,
            'unit_cost' => round($command->unitCost, 4),
            'reference_type' => $command->referenceType,
            'reference_id' => $command->referenceId,
            'reversal_of_id' => $command->reversalOfId,
            'reserved_stock_delta' => $command->reservedStockDelta,
            'in_transit_stock_delta' => $command->inTransitStockDelta,
        ];

        ksort($payload);

        return hash('sha256', json_encode(Arr::sortRecursive($payload), JSON_THROW_ON_ERROR));
    }

    private function assertScope(InventoryMovementCommand $command): void
    {
        $productExists = DB::table('products')->where('id', $command->productId)->where('organization_id', $command->organizationId)->exists();
        $branchExists = DB::table('security_branches')->where('id', $command->branchId)->where('organization_id', $command->organizationId)->exists();
        $warehouseExists = $command->warehouseId === null || DB::table('inventory_warehouses')
            ->where('id', $command->warehouseId)
            ->where('branch_id', $command->branchId)
            ->where('organization_id', $command->organizationId)
            ->exists();

        if (! $productExists || ! $branchExists || ! $warehouseExists) {
            throw ValidationException::withMessages([
                'inventory_scope' => 'Producto y ubicacion deben pertenecer a la misma organizacion.',
            ]);
        }
    }
}

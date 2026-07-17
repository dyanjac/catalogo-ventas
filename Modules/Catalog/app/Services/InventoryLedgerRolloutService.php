<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryReconciliationRun;
use Modules\Catalog\Entities\InventoryReservation;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Enums\InventoryReservationStatus;
use Modules\Catalog\Enums\InventoryTransferStatus;

class InventoryLedgerRolloutService
{
    public function setMode(int $organizationId, InventoryLedgerRolloutMode $mode): InventoryLedgerRollout
    {
        return DB::transaction(function () use ($organizationId, $mode): InventoryLedgerRollout {
            $rollout = InventoryLedgerRollout::query()
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if ($rollout?->mode === InventoryLedgerRolloutMode::Active && $mode !== InventoryLedgerRolloutMode::Active) {
                $hasActiveReservations = InventoryReservation::query()
                    ->where('organization_id', $organizationId)
                    ->where('status', InventoryReservationStatus::Active->value)
                    ->exists();
                if ($hasActiveReservations) {
                    throw ValidationException::withMessages([
                        'mode' => 'Libere todas las reservas activas antes de desactivar la lectura ledger.',
                    ]);
                }
                $hasTransit = InventoryBalance::query()
                    ->where('organization_id', $organizationId)
                    ->where('in_transit_stock', '>', 0)
                    ->exists();
                $hasOpenTransfers = InventoryTransfer::query()
                    ->where('organization_id', $organizationId)
                    ->whereIn('status', [InventoryTransferStatus::InTransit->value, InventoryTransferStatus::PartiallyReceived->value])
                    ->exists();
                if ($hasTransit || $hasOpenTransfers) {
                    throw ValidationException::withMessages([
                        'mode' => 'Complete las transferencias y deje el stock en transito en cero antes de desactivar el ledger.',
                    ]);
                }
            }

            $latestRun = InventoryReconciliationRun::query()
                ->where('organization_id', $organizationId)
                ->latest('id')
                ->first();

            if ($mode === InventoryLedgerRolloutMode::Active && (! $latestRun || $latestRun->status !== 'passed')) {
                throw ValidationException::withMessages([
                    'mode' => 'No se puede activar la lectura ledger sin una conciliacion exitosa.',
                ]);
            }

            $values = [
                'mode' => $mode->value,
                'reconciled_at' => $latestRun?->finished_at,
                'activated_at' => $mode === InventoryLedgerRolloutMode::Active ? now() : null,
                'last_summary' => $latestRun?->summary,
            ];
            if ($rollout) {
                $rollout->forceFill($values)->save();

                return $rollout;
            }

            return InventoryLedgerRollout::query()->create(['organization_id' => $organizationId, ...$values]);
        }, max(1, (int) config('catalog.reservations.transaction_attempts', 5)));
    }
}

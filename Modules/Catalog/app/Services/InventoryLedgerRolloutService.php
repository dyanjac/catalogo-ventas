<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryReconciliationRun;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;

class InventoryLedgerRolloutService
{
    public function setMode(int $organizationId, InventoryLedgerRolloutMode $mode): InventoryLedgerRollout
    {
        $latestRun = InventoryReconciliationRun::query()
            ->where('organization_id', $organizationId)
            ->latest('id')
            ->first();

        if ($mode === InventoryLedgerRolloutMode::Active && (! $latestRun || $latestRun->status !== 'passed')) {
            throw ValidationException::withMessages([
                'mode' => 'No se puede activar la lectura ledger sin una conciliacion exitosa.',
            ]);
        }

        return InventoryLedgerRollout::query()->updateOrCreate(
            ['organization_id' => $organizationId],
            [
                'mode' => $mode->value,
                'reconciled_at' => $latestRun?->finished_at,
                'activated_at' => $mode === InventoryLedgerRolloutMode::Active ? now() : null,
                'last_summary' => $latestRun?->summary,
            ]
        );
    }
}

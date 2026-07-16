<?php

declare(strict_types=1);

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Services\InventoryLedgerRolloutService;

class SetInventoryLedgerRolloutCommand extends Command
{
    protected $signature = 'inventory:ledger-rollout {organization : ID de organizacion} {mode : off, shadow o active}';

    protected $description = 'Cambia la fuente de lectura de inventario por organizacion';

    public function handle(InventoryLedgerRolloutService $service): int
    {
        $organizationId = (int) $this->argument('organization');
        $mode = InventoryLedgerRolloutMode::tryFrom((string) $this->argument('mode'));

        if ($organizationId < 1 || ! $mode) {
            $this->error('Use una organizacion positiva y un modo off, shadow o active.');

            return self::FAILURE;
        }

        $rollout = $service->setMode($organizationId, $mode);
        $this->info("Lectura de inventario configurada en {$rollout->mode->value}.");

        return self::SUCCESS;
    }
}

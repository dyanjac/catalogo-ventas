<?php

declare(strict_types=1);

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Catalog\Services\InventoryReconciliationService;

class ReconcileInventoryLedgerCommand extends Command
{
    protected $signature = 'inventory:ledger-reconcile {organization : ID de organizacion}';

    protected $description = 'Compara ledger, saldo objetivo y espejos legacy sin reparar datos';

    public function handle(InventoryReconciliationService $service): int
    {
        $organizationId = (int) $this->argument('organization');

        if ($organizationId < 1) {
            $this->error('La organizacion debe ser un entero positivo.');

            return self::FAILURE;
        }

        $run = $service->run($organizationId);
        $this->line("Conciliacion {$run->status}: {$run->checked_balances} saldos; {$run->issue_count} incidencias.");

        return $run->status === 'passed' ? self::SUCCESS : self::FAILURE;
    }
}

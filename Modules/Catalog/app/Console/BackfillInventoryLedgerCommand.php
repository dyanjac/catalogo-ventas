<?php

declare(strict_types=1);

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Catalog\Services\InventoryLedgerBackfillService;

class BackfillInventoryLedgerCommand extends Command
{
    protected $signature = 'inventory:ledger-backfill {--organization= : ID de organizacion} {--chunk=500 : Productos por lote} {--dry-run : Simular sin escribir}';

    protected $description = 'Construye saldos y movimientos baseline idempotentes desde los saldos legacy';

    public function handle(InventoryLedgerBackfillService $service): int
    {
        $organizationId = $this->option('organization');
        $chunk = (int) $this->option('chunk');

        if ($chunk < 1 || ($organizationId !== null && (! is_numeric($organizationId) || (int) $organizationId < 1))) {
            $this->error('Los parametros organization y chunk deben ser enteros positivos.');

            return self::FAILURE;
        }

        $stats = $service->run(
            $organizationId !== null ? (int) $organizationId : null,
            $chunk,
            (bool) $this->option('dry-run'),
        );

        $this->info(sprintf(
            'Organizaciones: %d; productos: %d; baselines: %d; omitidos: %d; dry-run: %s.',
            $stats['organizations'],
            $stats['products'],
            $stats['baselines'],
            $stats['skipped'],
            $stats['dry_run'] ? 'si' : 'no',
        ));

        return self::SUCCESS;
    }
}

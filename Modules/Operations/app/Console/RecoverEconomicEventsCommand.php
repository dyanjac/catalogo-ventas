<?php

declare(strict_types=1);

namespace Modules\Operations\Console;

use Illuminate\Console\Command;
use Modules\Operations\Services\OperationalRecoveryService;

final class RecoverEconomicEventsCommand extends Command
{
    protected $signature = 'operations:recover-events {organization : ID de organización} {--older-than=15} {--execute} {--confirm=}';

    protected $description = 'Detecta y recupera eventos contables abandonados en processing; por defecto solo simula';

    public function handle(OperationalRecoveryService $recovery): int
    {
        $organizationId = (int) $this->argument('organization');
        $execute = (bool) $this->option('execute');
        if ($execute && $this->option('confirm') !== "RECOVER:{$organizationId}") {
            $this->error("Para ejecutar use --confirm=RECOVER:{$organizationId}.");

            return self::INVALID;
        }

        $result = $recovery->recoverStaleEconomicEvents(
            $organizationId,
            max(1, (int) $this->option('older-than')),
            $execute,
        );

        $this->info(($execute ? 'Recuperados' : 'Detectados').": {$result['count']} evento(s).");
        if ($result['event_ids'] !== []) {
            $this->line('IDs: '.implode(', ', $result['event_ids']));
        }
        if (! $execute) {
            $this->comment('Simulación: no se modificó ningún evento.');
        }

        return self::SUCCESS;
    }
}

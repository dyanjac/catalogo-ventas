<?php

declare(strict_types=1);

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Catalog\Services\InventoryReservationService;

class ReleaseInventoryReservationsCommand extends Command
{
    protected $signature = 'inventory:reservations-release {organization : ID de organizacion} {--all-active : Liberar todas las reservas activas} {--dry-run : Solo contar reservas}';

    protected $description = 'Libera reservas activas antes de desactivar el rollout ledger';

    public function handle(InventoryReservationService $service): int
    {
        $organizationId = (int) $this->argument('organization');
        if ($organizationId < 1 || ! $this->option('all-active')) {
            $this->error('Indique una organizacion positiva y confirme con --all-active.');

            return self::FAILURE;
        }

        $result = $service->releaseAllActive($organizationId, (bool) $this->option('dry-run'));
        $this->info("Reservas activas: {$result['matched']}; liberadas: {$result['processed']}.");

        return self::SUCCESS;
    }
}

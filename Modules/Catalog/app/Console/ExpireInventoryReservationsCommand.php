<?php

declare(strict_types=1);

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Catalog\Services\InventoryReservationService;

class ExpireInventoryReservationsCommand extends Command
{
    protected $signature = 'inventory:reservations-expire {--organization= : Limitar a una organizacion} {--limit= : Maximo por ejecucion} {--dry-run : Solo contar vencidas}';

    protected $description = 'Expira reservas vencidas y libera su stock de forma idempotente';

    public function handle(InventoryReservationService $service): int
    {
        $organizationId = $this->option('organization') !== null ? (int) $this->option('organization') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        if (($organizationId !== null && $organizationId < 1) || ($limit !== null && $limit < 1)) {
            $this->error('organization y limit deben ser enteros positivos.');

            return self::FAILURE;
        }

        $result = $service->expireDue($organizationId, $limit, (bool) $this->option('dry-run'));
        $this->info("Reservas vencidas: {$result['matched']}; procesadas: {$result['processed']}.");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Console;

use Illuminate\Console\Command;
use Modules\Subscriptions\Jobs\ProcessSubscriptionAccrualJob;
use Modules\Subscriptions\Services\SubscriptionAccrualService;

class DispatchAccrualsCommand extends Command
{
    protected $signature = 'subscriptions:accruals:dispatch {--organization=} {--through=} {--limit=500} {--dry-run}';

    protected $description = 'Reclama y despacha devengamientos de suscripciones vencidos';

    public function handle(SubscriptionAccrualService $service): int
    {
        $through = (string) ($this->option('through') ?: now('UTC')->toDateString());
        if ($this->option('dry-run')) {
            $this->info('SimulaciÃ³n: no se reclamaron filas. Fecha de corte: '.$through);

            return self::SUCCESS;
        }
        $ids = $service->claimDue($through, (int) $this->option('limit'), $this->option('organization') ? (int) $this->option('organization') : null);
        foreach ($ids as $id) {
            $schedule = \Modules\Subscriptions\Models\SubscriptionAccrualSchedule::query()->find($id);
            if ($schedule) {
                ProcessSubscriptionAccrualJob::dispatch((int) $schedule->organization_id, $id);
            }
        }
        $this->info(count($ids).' devengamientos despachados.');

        return self::SUCCESS;
    }
}

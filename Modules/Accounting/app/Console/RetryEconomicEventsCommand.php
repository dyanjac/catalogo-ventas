<?php

namespace Modules\Accounting\Console;

use Illuminate\Console\Command;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Jobs\ProcessEconomicEventJob;
use Modules\Accounting\Models\AccountingEconomicEvent;

class RetryEconomicEventsCommand extends Command
{
    protected $signature = 'accounting:events:retry {--organization=} {--limit=100}';

    protected $description = 'Reencola eventos económicos pendientes o fallidos de forma idempotente';

    public function handle(): int
    {
        $organizationId = $this->option('organization');
        $events = AccountingEconomicEvent::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', (int) $organizationId))
            ->whereIn('status', [EconomicEventStatus::Pending->value, EconomicEventStatus::Error->value])
            ->where(fn ($query) => $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->orderBy('id')
            ->limit(max(1, min(1000, (int) $this->option('limit'))))
            ->get();

        foreach ($events as $event) {
            if ($event->status === EconomicEventStatus::Error) {
                $event->forceFill(['status' => EconomicEventStatus::Pending, 'next_retry_at' => null])->save();
            }
            ProcessEconomicEventJob::dispatch((int) $event->organization_id, (int) $event->id);
        }

        $this->info($events->count().' evento(s) reencolado(s).');

        return self::SUCCESS;
    }
}

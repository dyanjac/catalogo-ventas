<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Console;

use Illuminate\Console\Command;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\CustomerSubscription;
use Modules\Subscriptions\Services\SubscriptionLifecycleService;

class RenewSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:renewals:dispatch {--organization=} {--through=} {--limit=500} {--dry-run}';

    protected $description = 'Genera el siguiente periodo de las suscripciones renovables';

    public function handle(SubscriptionLifecycleService $service): int
    {
        $through = (string) ($this->option('through') ?: now('UTC')->toDateString());
        $finalized = $this->option('dry-run') ? 0 : $service->finalizeDue($through, $this->option('organization') ? (int) $this->option('organization') : null);
        $rows = CustomerSubscription::query()->where('status', SubscriptionStatus::Active->value)
            ->where('cancel_at_period_end', false)->whereDate('next_renewal_on', '<=', $through)
            ->when($this->option('organization'), fn ($q) => $q->where('organization_id', (int) $this->option('organization')))
            ->orderBy('next_renewal_on')->orderBy('id')->limit((int) $this->option('limit'))->get();
        if ($this->option('dry-run')) {
            $this->info($rows->count().' suscripciones renovables.');

            return self::SUCCESS;
        }
        foreach ($rows as $subscription) {
            $service->renew($subscription, "subscription:{$subscription->id}:renewal:".($subscription->renewal_count + 1));
        }
        $this->info($rows->count().' suscripciones renovadas; '.$finalized.' finalizadas.');

        return self::SUCCESS;
    }
}

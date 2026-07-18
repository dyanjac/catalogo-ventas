<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Jobs\ProcessEconomicEventJob;
use Modules\Accounting\Models\AccountingEconomicEvent;

final class OperationalRecoveryService
{
    /** @return array{organization_id:int,execute:bool,cutoff:string,count:int,event_ids:list<int>} */
    public function recoverStaleEconomicEvents(int $organizationId, int $olderThanMinutes, bool $execute): array
    {
        Organization::query()->findOrFail($organizationId);
        $cutoff = now()->subMinutes(max(1, $olderThanMinutes));

        $eventIds = AccountingEconomicEvent::query()
            ->where('organization_id', $organizationId)
            ->where('status', EconomicEventStatus::Processing->value)
            ->whereNull('processed_entry_id')
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(1000)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($execute && $eventIds !== []) {
            $recoveredIds = [];
            DB::transaction(function () use ($organizationId, $eventIds, $cutoff, &$recoveredIds): void {
                Organization::query()->whereKey($organizationId)->lockForUpdate()->firstOrFail();

                AccountingEconomicEvent::query()
                    ->where('organization_id', $organizationId)
                    ->whereIn('id', $eventIds)
                    ->where('status', EconomicEventStatus::Processing->value)
                    ->whereNull('processed_entry_id')
                    ->where('updated_at', '<=', $cutoff)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (AccountingEconomicEvent $event) use (&$recoveredIds): void {
                        $event->forceFill([
                            'status' => EconomicEventStatus::Pending,
                            'next_retry_at' => null,
                            'error_code' => 'stale_processing_recovered',
                            'error_message' => null,
                        ])->save();
                        $recoveredIds[] = (int) $event->id;
                        ProcessEconomicEventJob::dispatch((int) $event->organization_id, (int) $event->id);
                    });
            });
            $eventIds = $recoveredIds;
        }

        Log::channel('operations')->notice('erp.accounting.stale_events_recovery', [
            'organization_id' => $organizationId,
            'execute' => $execute,
            'count' => count($eventIds),
            'event_ids' => $eventIds,
        ]);

        return [
            'organization_id' => $organizationId,
            'execute' => $execute,
            'cutoff' => $cutoff->toIso8601String(),
            'count' => count($eventIds),
            'event_ids' => $eventIds,
        ];
    }
}

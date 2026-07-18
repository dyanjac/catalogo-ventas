<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use App\Models\Organization;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Operations\Models\OperationalIncident;
use Modules\Operations\Models\OperationalIncidentEvent;
use Modules\Operations\Models\OperationalReconciliationRun;

final class OperationalIncidentService
{
    public function synchronize(OperationalReconciliationRun $run): void
    {
        DB::transaction(function () use ($run): void {
            Organization::query()->lockForUpdate()->findOrFail($run->organization_id);
            $seen = [];
            foreach ($run->issues()->orderBy('id')->get() as $issue) {
                $seen[] = $issue->fingerprint;
                $incident = OperationalIncident::query()->where('organization_id', $run->organization_id)
                    ->where('fingerprint', $issue->fingerprint)->lockForUpdate()->first();
                if (! $incident) {
                    $incident = OperationalIncident::query()->create([
                        'organization_id' => $run->organization_id, 'fingerprint' => $issue->fingerprint,
                        'domain' => $issue->domain, 'issue_code' => $issue->issue_code,
                        'severity' => $issue->severity, 'status' => 'open', 'source_type' => $issue->source_type,
                        'source_id' => $issue->source_id, 'source_code' => $issue->source_code,
                        'first_seen_at' => $run->captured_at, 'last_seen_at' => $run->captured_at,
                        'occurrences' => 1, 'latest_run_id' => $run->id, 'context' => $issue->context,
                    ]);
                    $this->event($incident, 'opened', $run);
                } else {
                    $eventType = $incident->status === 'resolved' ? 'reopened' : 'repeated';
                    $incident->forceFill([
                        'severity' => $issue->severity,
                        'status' => $incident->status === 'resolved' ? 'open' : $incident->status,
                        'last_seen_at' => $run->captured_at, 'resolved_at' => null,
                        'occurrences' => $incident->occurrences + 1, 'latest_run_id' => $run->id,
                        'context' => $issue->context,
                    ])->save();
                    $this->event($incident, $eventType, $run);
                }
            }

            OperationalIncident::query()->where('organization_id', $run->organization_id)
                ->whereIn('status', ['open', 'acknowledged'])
                ->when($seen !== [], fn ($query) => $query->whereNotIn('fingerprint', $seen))
                ->orderBy('id')->lockForUpdate()->get()->each(function (OperationalIncident $incident) use ($run): void {
                    $incident->forceFill(['status' => 'resolved', 'resolved_at' => $run->captured_at, 'latest_run_id' => $run->id])->save();
                    $this->event($incident, 'resolved', $run);
                });
        }, 3);

        Log::channel('operations')->info('erp.incidents.synchronized', [
            'organization_id' => $run->organization_id, 'run_id' => $run->id,
            'correlation_id' => $run->correlation_id, 'issue_count' => $run->issue_count,
        ]);
    }

    public function acknowledge(OperationalIncident $incident, int $actorId, ?string $note = null): OperationalIncident
    {
        if ($incident->status === 'resolved') {
            throw new DomainException('Un incidente resuelto no puede reconocerse.');
        }
        return DB::transaction(function () use ($incident, $actorId, $note): OperationalIncident {
            $normalizedNote = trim($note ?? '');
            $locked = OperationalIncident::query()->where('organization_id', $incident->organization_id)
                ->lockForUpdate()->findOrFail($incident->id);
            $locked->forceFill([
                'status' => 'acknowledged', 'acknowledged_by' => $actorId,
                'acknowledged_at' => now('UTC'), 'acknowledgement_note' => $normalizedNote !== '' ? $normalizedNote : null,
            ])->save();
            $this->event($locked, 'acknowledged', $locked->latestRun, $actorId,
                $normalizedNote !== '' ? ['note' => $normalizedNote] : []);

            return $locked->fresh(['events', 'acknowledger']);
        }, 3);
    }

    private function event(OperationalIncident $incident, string $type, ?OperationalReconciliationRun $run, ?int $actorId = null, array $context = []): void
    {
        OperationalIncidentEvent::query()->create([
            'incident_id' => $incident->id, 'organization_id' => $incident->organization_id,
            'run_id' => $run?->id, 'event_type' => $type, 'context' => $context ?: null,
            'actor_id' => $actorId, 'occurred_at' => now('UTC'),
        ]);
    }
}

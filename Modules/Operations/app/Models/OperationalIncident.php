<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use App\Services\OrganizationContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class OperationalIncident extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'fingerprint', 'domain', 'issue_code', 'severity', 'status',
        'source_type', 'source_id', 'source_code', 'first_seen_at', 'last_seen_at',
        'resolved_at', 'occurrences', 'latest_run_id', 'context', 'acknowledged_by',
        'acknowledged_at', 'acknowledgement_note',
    ];

    protected $casts = [
        'organization_id' => 'integer', 'source_id' => 'integer', 'occurrences' => 'integer',
        'context' => 'array', 'first_seen_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime',
        'resolved_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $incident): void {
            if ($incident->isDirty(['organization_id', 'fingerprint', 'domain', 'issue_code', 'source_type', 'source_id'])) {
                throw new LogicException('La identidad del incidente operativo es inmutable.');
            }
        });
    }

    public function latestRun(): BelongsTo
    {
        return $this->belongsTo(OperationalReconciliationRun::class, 'latest_run_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OperationalIncidentEvent::class, 'incident_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

        return $query->where('organization_id', $organizationId ?: 0)
            ->where($field ?? $this->getRouteKeyName(), $value);
    }
}

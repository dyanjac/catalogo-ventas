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

final class OperationalReconciliationRun extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'correlation_id', 'trigger', 'status', 'captured_at', 'started_at',
        'finished_at', 'duration_ms', 'checked_inventory_balances', 'checked_inventory_documents',
        'checked_economic_events', 'checked_accounting_entries', 'issue_count', 'critical_count',
        'warning_count', 'metrics', 'error_code', 'error_message', 'created_by',
    ];

    protected $casts = [
        'organization_id' => 'integer', 'duration_ms' => 'integer', 'checked_inventory_balances' => 'integer',
        'checked_inventory_documents' => 'integer', 'checked_economic_events' => 'integer',
        'checked_accounting_entries' => 'integer', 'issue_count' => 'integer', 'critical_count' => 'integer',
        'warning_count' => 'integer', 'metrics' => 'array', 'captured_at' => 'immutable_datetime',
        'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            if ($run->isDirty(['organization_id', 'correlation_id', 'trigger', 'captured_at', 'started_at', 'created_by'])) {
                throw new LogicException('La identidad de la corrida operativa es inmutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Las corridas operativas son evidencia inmutable.'));
    }

    public function issues(): HasMany
    {
        return $this->hasMany(OperationalReconciliationIssue::class, 'run_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(OperationalIncident::class, 'latest_run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

        return $query->where('organization_id', $organizationId ?: 0)
            ->where($field ?? $this->getRouteKeyName(), $value);
    }
}

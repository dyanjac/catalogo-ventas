<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use App\Services\OrganizationContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class AccountingActivationRun extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'status', 'cutoff_at', 'captured_through_at', 'simulation_hash',
        'confirmation_token', 'configuration_snapshot', 'summary', 'eligible_count',
        'existing_count', 'error_count', 'processed_count', 'created_by', 'confirmed_by',
        'confirmed_at', 'started_at', 'completed_at', 'error_code', 'error_message',
    ];

    protected $casts = [
        'organization_id' => 'integer', 'configuration_snapshot' => 'array', 'summary' => 'array',
        'eligible_count' => 'integer', 'existing_count' => 'integer', 'error_count' => 'integer',
        'processed_count' => 'integer', 'cutoff_at' => 'immutable_datetime',
        'captured_through_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime',
        'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            if ($run->getRawOriginal('status') !== 'simulating' && $run->isDirty([
                'organization_id', 'cutoff_at', 'captured_through_at', 'simulation_hash',
                'confirmation_token', 'configuration_snapshot', 'summary', 'eligible_count',
                'existing_count', 'error_count', 'created_by',
            ])) {
                throw new LogicException('El snapshot de una activación simulada es inmutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Las corridas históricas son evidencia inmutable.'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccountingActivationItem::class, 'activation_run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

        return $query->where('organization_id', $organizationId ?: 0)
            ->where($field ?? $this->getRouteKeyName(), $value);
    }
}

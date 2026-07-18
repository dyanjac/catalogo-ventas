<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class OperationalIncidentEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['incident_id', 'organization_id', 'run_id', 'event_type', 'context', 'actor_id', 'occurred_at'];

    protected $casts = ['organization_id' => 'integer', 'context' => 'array', 'occurred_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos de incidente son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los eventos de incidente son inmutables.'));
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(OperationalIncident::class, 'incident_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(OperationalReconciliationRun::class, 'run_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

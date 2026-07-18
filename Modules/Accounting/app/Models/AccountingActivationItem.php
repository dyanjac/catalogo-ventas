<?php

declare(strict_types=1);

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class AccountingActivationItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'activation_run_id', 'organization_id', 'event_type', 'source_type', 'source_id',
        'source_code', 'occurred_at', 'idempotency_key', 'payload_hash', 'simulation_hash',
        'status', 'dependency_order', 'dependency_key', 'payload', 'configuration_snapshot',
        'issues', 'accounting_economic_event_id', 'accounting_entry_id', 'processed_at',
    ];

    protected $casts = [
        'organization_id' => 'integer', 'source_id' => 'integer', 'dependency_order' => 'integer',
        'payload' => 'array', 'configuration_snapshot' => 'array', 'issues' => 'array',
        'occurred_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            if ($item->isDirty([
                'activation_run_id', 'organization_id', 'event_type', 'source_type', 'source_id',
                'source_code', 'occurred_at', 'idempotency_key', 'payload_hash', 'simulation_hash',
                'dependency_order', 'dependency_key', 'payload', 'configuration_snapshot', 'issues',
            ])) {
                throw new LogicException('El candidato histórico sellado es inmutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los candidatos históricos son evidencia inmutable.'));
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AccountingActivationRun::class, 'activation_run_id');
    }

    public function economicEvent(): BelongsTo
    {
        return $this->belongsTo(AccountingEconomicEvent::class, 'accounting_economic_event_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }
}

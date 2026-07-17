<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Enums\EconomicEventType;

class AccountingEconomicEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'branch_id', 'event_type', 'status', 'idempotency_key',
        'payload_hash', 'source_type', 'source_id', 'source_code', 'payload',
        'configuration_snapshot', 'processed_entry_id', 'attempts', 'error_code',
        'error_message', 'occurred_at', 'processed_at', 'next_retry_at',
        'created_by', 'reversal_of_event_id',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'branch_id' => 'integer',
        'source_id' => 'integer',
        'processed_entry_id' => 'integer',
        'attempts' => 'integer',
        'payload' => 'array',
        'configuration_snapshot' => 'array',
        'event_type' => EconomicEventType::class,
        'status' => EconomicEventStatus::class,
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $event): void {
            $immutable = ['organization_id', 'event_type', 'idempotency_key', 'payload_hash', 'source_type', 'source_id', 'payload'];
            if ($event->isDirty($immutable)) {
                throw new LogicException('La identidad y el payload del evento económico son inmutables.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los eventos económicos son inmutables.'));
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'processed_entry_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_event_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        return $query->forCurrentOrganization()->where($field ?? $this->getRouteKeyName(), $value);
    }
}

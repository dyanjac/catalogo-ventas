<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Subscriptions\Enums\AccrualStatus;

class SubscriptionAccrualSchedule extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'subscription_id', 'service_period_id', 'sequence', 'revision',
        'kind', 'status', 'idempotency_key', 'payload_hash', 'service_starts_on',
        'service_ends_on', 'due_on', 'amount_minor', 'currency', 'reason',
        'accounting_economic_event_id', 'lease_token', 'claimed_at', 'attempts',
        'error_code', 'error_message', 'event_recorded_at',
    ];

    protected $casts = [
        'sequence' => 'integer', 'revision' => 'integer', 'status' => AccrualStatus::class,
        'service_starts_on' => 'immutable_date', 'service_ends_on' => 'immutable_date',
        'due_on' => 'immutable_date', 'amount_minor' => 'integer', 'claimed_at' => 'immutable_datetime',
        'attempts' => 'integer', 'event_recorded_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $schedule): void {
            if ($schedule->isDirty([
                'organization_id', 'subscription_id', 'service_period_id', 'sequence', 'revision',
                'kind', 'idempotency_key', 'payload_hash', 'service_starts_on', 'service_ends_on',
                'due_on', 'amount_minor', 'currency', 'reason',
            ])) {
                throw new LogicException('La identidad financiera del devengamiento es inmutable.');
            }
            if ($schedule->getRawOriginal('status') !== AccrualStatus::EventRecorded->value) {
                return;
            }
            $immutable = [
                'organization_id', 'subscription_id', 'service_period_id', 'sequence', 'revision',
                'kind', 'status', 'idempotency_key', 'payload_hash', 'service_starts_on',
                'service_ends_on', 'due_on', 'amount_minor', 'currency', 'reason',
                'accounting_economic_event_id',
            ];
            if ($schedule->isDirty($immutable)) {
                throw new LogicException('Un devengamiento con evento registrado es inmutable.');
            }
        });
        static::deleting(function (self $schedule): void {
            if ($schedule->status === AccrualStatus::EventRecorded) {
                throw new LogicException('Un devengamiento con evento registrado no puede eliminarse.');
            }
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'subscription_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SubscriptionServicePeriod::class, 'service_period_id');
    }

    public function economicEvent(): BelongsTo
    {
        return $this->belongsTo(AccountingEconomicEvent::class, 'accounting_economic_event_id');
    }
}

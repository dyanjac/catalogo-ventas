<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\Billing\Models\BillingDocument;

class SubscriptionServicePeriod extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'subscription_id', 'sequence', 'status', 'idempotency_key',
        'payload_hash', 'service_starts_on', 'service_ends_on', 'billing_due_on',
        'subtotal_minor', 'tax_minor', 'total_minor', 'accounting_snapshot', 'billing_document_id', 'renewed_at',
    ];

    protected $casts = [
        'sequence' => 'integer', 'service_starts_on' => 'immutable_date',
        'service_ends_on' => 'immutable_date', 'billing_due_on' => 'immutable_date',
        'subtotal_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer',
        'renewed_at' => 'immutable_datetime',
        'accounting_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $period): void {
            if ($period->isDirty([
                'organization_id', 'subscription_id', 'sequence', 'idempotency_key', 'payload_hash',
                'service_starts_on', 'service_ends_on', 'billing_due_on', 'subtotal_minor',
                'tax_minor', 'total_minor',
            ])) {
                throw new LogicException('Los tÃ©rminos financieros del periodo son inmutables.');
            }
            if ($period->getRawOriginal('billing_document_id') !== null
                && $period->isDirty('billing_document_id')) {
                throw new LogicException('El comprobante del periodo no puede reemplazarse.');
            }
            if ($period->getRawOriginal('accounting_snapshot') !== null
                && $period->isDirty('accounting_snapshot')) {
                throw new LogicException('El snapshot contable del periodo no puede reemplazarse.');
            }
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'subscription_id');
    }

    public function billingDocument(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(SubscriptionAccrualSchedule::class, 'service_period_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use App\Services\OrganizationContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\Catalog\Entities\Product;
use Modules\Security\Models\SecurityBranch;
use Modules\Subscriptions\Enums\SubscriptionStatus;

class CustomerSubscription extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'branch_id', 'customer_id', 'product_id', 'source_order_item_id',
        'code', 'idempotency_key', 'payload_hash', 'status', 'currency', 'billing_cycle_months',
        'recurring_subtotal_minor', 'recurring_tax_minor', 'recurring_total_minor',
        'service_starts_on', 'current_period_starts_on', 'current_period_ends_on',
        'next_renewal_on', 'ends_on', 'cancel_at_period_end', 'cancelled_at',
        'cancellation_reason', 'renewal_count', 'version', 'created_by',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'billing_cycle_months' => 'integer',
        'recurring_subtotal_minor' => 'integer',
        'recurring_tax_minor' => 'integer',
        'recurring_total_minor' => 'integer',
        'service_starts_on' => 'immutable_date',
        'current_period_starts_on' => 'immutable_date',
        'current_period_ends_on' => 'immutable_date',
        'next_renewal_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
        'cancel_at_period_end' => 'boolean',
        'cancelled_at' => 'immutable_datetime',
        'renewal_count' => 'integer',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $subscription): void {
            if ($subscription->isDirty([
                'organization_id', 'branch_id', 'customer_id', 'product_id',
                'code', 'idempotency_key', 'payload_hash', 'currency', 'billing_cycle_months',
                'recurring_subtotal_minor', 'recurring_tax_minor', 'recurring_total_minor',
                'service_starts_on', 'created_by',
            ])) {
                throw new LogicException('Los tÃ©rminos econÃ³micos del contrato son inmutables.');
            }
            if ($subscription->getRawOriginal('source_order_item_id') !== null
                && $subscription->isDirty('source_order_item_id')) {
                throw new LogicException('El origen comercial del contrato no puede reemplazarse.');
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(SubscriptionServicePeriod::class, 'subscription_id');
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(SubscriptionAccrualSchedule::class, 'subscription_id');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

        return $query->where('organization_id', $organizationId ?: 0)->where($field ?? $this->getRouteKeyName(), $value);
    }
}

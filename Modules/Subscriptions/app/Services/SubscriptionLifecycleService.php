<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Services;

use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductType;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Orders\Entities\OrderItem;
use Modules\Security\Models\SecurityBranch;
use Modules\Subscriptions\Enums\AccrualStatus;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\CustomerSubscription;
use Modules\Subscriptions\Models\SubscriptionAccrualSchedule;
use Modules\Subscriptions\Models\SubscriptionServicePeriod;

final class SubscriptionLifecycleService
{
    public function __construct(
        private readonly AccrualScheduleAllocator $allocator,
        private readonly OrganizationEntitlementService $entitlements,
    ) {}

    /** @param array<string,mixed> $data */
    public function activate(array $data): CustomerSubscription
    {
        $organizationId = (int) ($data['organization_id'] ?? 0);
        $product = Product::query()->where('organization_id', $organizationId)->findOrFail((int) $data['product_id']);
        User::query()->where('organization_id', $organizationId)->findOrFail((int) $data['customer_id']);
        if (isset($data['branch_id'])) {
            SecurityBranch::query()->where('organization_id', $organizationId)->findOrFail((int) $data['branch_id']);
        }
        if (isset($data['source_order_item_id'])) {
            OrderItem::query()->where('organization_id', $organizationId)
                ->where('product_id', $product->id)->findOrFail((int) $data['source_order_item_id']);
        }
        if ($product->product_type !== ProductType::Subscription) {
            throw new DomainException('Solo un producto de tipo suscripciÃ³n puede activar este contrato.');
        }

        $currency = strtoupper((string) ($data['currency'] ?? 'PEN'));
        $accountingCurrency = AccountingSetting::query()->where('organization_id', $organizationId)->value('default_currency');
        if (! $accountingCurrency || $currency !== strtoupper((string) $accountingCurrency)) {
            throw new DomainException('La moneda debe coincidir con la moneda contable de la organizaciÃ³n.');
        }

        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw new DomainException('La clave idempotente es obligatoria.');
        }
        $months = max(1, (int) ($data['billing_cycle_months'] ?? 1));
        $start = CarbonImmutable::parse((string) $data['service_starts_on'], 'UTC')->startOfDay();
        $end = $start->addMonthsNoOverflow($months);
        $normalized = [
            'branch_id' => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            'customer_id' => (int) $data['customer_id'], 'product_id' => (int) $product->id,
            'currency' => $currency, 'months' => $months,
            'subtotal_minor' => (int) $data['recurring_subtotal_minor'],
            'tax_minor' => (int) ($data['recurring_tax_minor'] ?? 0), 'starts_on' => $start->toDateString(),
            'source_order_item_id' => isset($data['source_order_item_id']) ? (int) $data['source_order_item_id'] : null,
            'code' => filled($data['code'] ?? null) ? trim((string) $data['code']) : null,
        ];
        $hash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($organizationId, $data, $key, $hash, $normalized, $start, $end): CustomerSubscription {
                $existing = CustomerSubscription::query()->where('organization_id', $organizationId)->where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    if (! hash_equals($existing->payload_hash, $hash)) {
                        throw new DomainException('La clave idempotente ya fue usada con otro payload.');
                    }

                    return $existing;
                }

                $subscription = CustomerSubscription::query()->create([
                    'organization_id' => $organizationId, 'branch_id' => $normalized['branch_id'],
                    'customer_id' => $normalized['customer_id'], 'product_id' => $normalized['product_id'],
                    'source_order_item_id' => $normalized['source_order_item_id'],
                    'code' => (string) ($normalized['code'] ?? 'SUB-'.strtoupper(substr(hash('sha256', $key), 0, 10))),
                    'idempotency_key' => $key, 'payload_hash' => $hash, 'status' => SubscriptionStatus::Active,
                    'currency' => $normalized['currency'], 'billing_cycle_months' => $normalized['months'],
                    'recurring_subtotal_minor' => $normalized['subtotal_minor'], 'recurring_tax_minor' => $normalized['tax_minor'],
                    'recurring_total_minor' => $normalized['subtotal_minor'] + $normalized['tax_minor'],
                    'service_starts_on' => $start, 'current_period_starts_on' => $start,
                    'current_period_ends_on' => $end, 'next_renewal_on' => $end,
                    'renewal_count' => 0, 'version' => 1, 'created_by' => $data['created_by'] ?? null,
                ]);
                $this->createPeriod($subscription, 1, $start, $end, "{$key}:period:1");

                return $subscription->fresh(['periods.accruals']);
            }, 3);
        } catch (QueryException $e) {
            $existing = CustomerSubscription::query()->where('organization_id', $organizationId)->where('idempotency_key', $key)->first();
            if ($existing && hash_equals($existing->payload_hash, $hash)) {
                return $existing;
            }
            throw $e;
        }
    }

    public function renew(CustomerSubscription $subscription, string $idempotencyKey): SubscriptionServicePeriod
    {
        return DB::transaction(function () use ($subscription, $idempotencyKey): SubscriptionServicePeriod {
            $locked = CustomerSubscription::query()->where('organization_id', $subscription->organization_id)->lockForUpdate()->findOrFail($subscription->id);
            $organization = Organization::query()->findOrFail($locked->organization_id);
            if ($organization->isSuspended() || ! $this->entitlements->hasCapability('subscriptions.recurring', $organization)) {
                throw new DomainException('La organizaciÃ³n no puede renovar suscripciones en su estado o plan actual.');
            }
            if ($locked->status !== SubscriptionStatus::Active || $locked->cancel_at_period_end) {
                throw new DomainException('La suscripciÃ³n no admite una nueva renovaciÃ³n.');
            }
            $existing = SubscriptionServicePeriod::query()->where('organization_id', $locked->organization_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $existingHash = hash('sha256', json_encode([
                    $locked->id, $existing->sequence, $existing->service_starts_on->toDateString(),
                    $existing->service_ends_on->toDateString(), $existing->subtotal_minor,
                ], JSON_THROW_ON_ERROR));
                if ((int) $existing->subscription_id !== (int) $locked->id || ! hash_equals($existing->payload_hash, $existingHash)) {
                    throw new DomainException('La clave idempotente de renovaciÃ³n ya fue usada por otro periodo.');
                }

                return $existing;
            }
            $sequence = $locked->renewal_count + 2;
            $anchor = CarbonImmutable::parse($locked->service_starts_on, 'UTC');
            $start = $anchor->addMonthsNoOverflow(($sequence - 1) * $locked->billing_cycle_months);
            $end = $anchor->addMonthsNoOverflow($sequence * $locked->billing_cycle_months);
            $period = $this->createPeriod($locked, $sequence, $start, $end, $idempotencyKey);
            $locked->forceFill([
                'current_period_starts_on' => $start, 'current_period_ends_on' => $end,
                'next_renewal_on' => $end, 'renewal_count' => $locked->renewal_count + 1,
                'version' => $locked->version + 1,
            ])->save();

            return $period->fresh('accruals');
        }, 3);
    }

    public function cancel(CustomerSubscription $subscription, bool $immediately, string $reason): CustomerSubscription
    {
        return DB::transaction(function () use ($subscription, $immediately, $reason): CustomerSubscription {
            $locked = CustomerSubscription::query()->where('organization_id', $subscription->organization_id)->lockForUpdate()->findOrFail($subscription->id);
            if ($locked->status === SubscriptionStatus::Cancelled) {
                return $locked;
            }
            if ($immediately) {
                if (now('UTC')->startOfDay()->greaterThan($locked->current_period_starts_on)) {
                    throw new DomainException('La cancelaciÃ³n intraperiodo requiere prorrateo y nota de crÃ©dito; use cancelaciÃ³n al cierre.');
                }
                SubscriptionAccrualSchedule::query()->where('subscription_id', $locked->id)
                    ->whereIn('status', [AccrualStatus::Pending->value, AccrualStatus::Error->value, AccrualStatus::Claimed->value])
                    ->whereDate('due_on', '>=', now('UTC')->toDateString())->update(['status' => AccrualStatus::Cancelled->value, 'updated_at' => now()]);
                $locked->forceFill(['status' => SubscriptionStatus::Cancelled, 'ends_on' => now('UTC')->toDateString(), 'cancel_at_period_end' => false]);
            } else {
                $locked->forceFill(['cancel_at_period_end' => true, 'ends_on' => $locked->current_period_ends_on]);
            }
            $locked->forceFill(['cancelled_at' => now('UTC'), 'cancellation_reason' => $reason, 'version' => $locked->version + 1])->save();

            return $locked->fresh();
        }, 3);
    }

    public function adjust(CustomerSubscription $subscription, int $amountMinor, CarbonImmutable $dueOn, string $reason, string $idempotencyKey): SubscriptionAccrualSchedule
    {
        if ($amountMinor >= 0 || trim($reason) === '') {
            throw new DomainException('Solo se admiten ajustes negativos contra ingreso ya devengado y con motivo.');
        }

        $payload = [$subscription->id, $amountMinor, $dueOn->toDateString(), $reason];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($subscription, $amountMinor, $dueOn, $reason, $idempotencyKey, $hash): SubscriptionAccrualSchedule {
            $lockedSubscription = CustomerSubscription::query()->where('organization_id', $subscription->organization_id)->lockForUpdate()->findOrFail($subscription->id);
            $existing = SubscriptionAccrualSchedule::query()->where('organization_id', $subscription->organization_id)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new DomainException('La clave idempotente del ajuste ya fue usada con otro payload.');
                }

                return $existing;
            }
            $earned = (int) SubscriptionAccrualSchedule::query()->where('subscription_id', $lockedSubscription->id)
                ->where('status', AccrualStatus::EventRecorded->value)->sum('amount_minor');
            $reserved = abs((int) SubscriptionAccrualSchedule::query()->where('subscription_id', $lockedSubscription->id)
                ->where('kind', 'adjustment')->whereIn('status', [AccrualStatus::Pending->value, AccrualStatus::Claimed->value, AccrualStatus::Error->value])
                ->where('amount_minor', '<', 0)->sum('amount_minor'));
            if (abs($amountMinor) > max(0, $earned - $reserved)) {
                throw new DomainException('El ajuste excede el ingreso devengado disponible.');
            }
            $period = $lockedSubscription->periods()->latest('sequence')->firstOrFail();

            return SubscriptionAccrualSchedule::query()->create([
                'organization_id' => $lockedSubscription->organization_id, 'subscription_id' => $lockedSubscription->id,
                'service_period_id' => $period->id, 'sequence' => $period->accruals()->max('sequence') + 1,
                'revision' => 2, 'kind' => 'adjustment', 'status' => AccrualStatus::Pending,
                'idempotency_key' => $idempotencyKey, 'payload_hash' => $hash,
                'service_starts_on' => $dueOn, 'service_ends_on' => $dueOn->addDay(), 'due_on' => $dueOn,
                'amount_minor' => $amountMinor, 'currency' => $lockedSubscription->currency, 'reason' => $reason,
            ]);
        }, 3);
    }

    public function finalizeDue(string $through, ?int $organizationId = null): int
    {
        return DB::transaction(function () use ($through, $organizationId): int {
            $rows = CustomerSubscription::query()->where('status', SubscriptionStatus::Active->value)
                ->where('cancel_at_period_end', true)->whereDate('ends_on', '<=', $through)
                ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($rows as $row) {
                $row->forceFill(['status' => SubscriptionStatus::Ended, 'version' => $row->version + 1])->save();
            }

            return $rows->count();
        }, 3);
    }

    private function createPeriod(CustomerSubscription $subscription, int $sequence, CarbonImmutable $start, CarbonImmutable $end, string $key): SubscriptionServicePeriod
    {
        $payload = [$subscription->id, $sequence, $start->toDateString(), $end->toDateString(), $subscription->recurring_subtotal_minor];
        $period = SubscriptionServicePeriod::query()->create([
            'organization_id' => $subscription->organization_id, 'subscription_id' => $subscription->id,
            'sequence' => $sequence, 'status' => 'scheduled', 'idempotency_key' => $key,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'service_starts_on' => $start, 'service_ends_on' => $end, 'billing_due_on' => $start,
            'subtotal_minor' => $subscription->recurring_subtotal_minor, 'tax_minor' => $subscription->recurring_tax_minor,
            'total_minor' => $subscription->recurring_total_minor, 'renewed_at' => $sequence > 1 ? now('UTC') : null,
        ]);
        foreach ($this->allocator->allocate($start, $end, $subscription->recurring_subtotal_minor) as $index => $slice) {
            $accrualKey = "{$key}:accrual:".($index + 1);
            $identity = [$period->id, $index + 1, $slice['starts_on']->toDateString(), $slice['ends_on']->toDateString(), $slice['amount_minor']];
            $period->accruals()->create([
                'organization_id' => $subscription->organization_id, 'subscription_id' => $subscription->id,
                'sequence' => $index + 1, 'revision' => 1, 'kind' => 'regular', 'status' => AccrualStatus::Pending,
                'idempotency_key' => $accrualKey, 'payload_hash' => hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR)),
                'service_starts_on' => $slice['starts_on'], 'service_ends_on' => $slice['ends_on'],
                'due_on' => $slice['due_on'], 'amount_minor' => $slice['amount_minor'], 'currency' => $subscription->currency,
            ]);
        }

        return $period;
    }
}

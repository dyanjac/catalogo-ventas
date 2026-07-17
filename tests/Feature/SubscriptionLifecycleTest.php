<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Commerce\Entities\SaasPlan;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\SubscriptionAccrualSchedule;
use Modules\Subscriptions\Services\SubscriptionAccrualService;
use Modules\Subscriptions\Services\SubscriptionLifecycleService;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_is_idempotent_and_generates_an_exact_annual_schedule(): void
    {
        [$organization, $customer, $product] = $this->fixture('SUB-A');
        $payload = [
            'organization_id' => $organization->id, 'customer_id' => $customer->id,
            'product_id' => $product->id, 'idempotency_key' => 'activate-sub-a',
            'service_starts_on' => '2026-01-31', 'billing_cycle_months' => 12,
            'currency' => 'PEN', 'recurring_subtotal_minor' => 10_001, 'recurring_tax_minor' => 1_800,
        ];

        $first = app(SubscriptionLifecycleService::class)->activate($payload);
        $replay = app(SubscriptionLifecycleService::class)->activate($payload);

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(10_001, $first->accruals()->sum('amount_minor'));
        $this->assertSame('2027-01-31', $first->current_period_ends_on->toDateString());
        $this->assertGreaterThanOrEqual(12, $first->accruals()->count());
    }

    public function test_same_idempotency_key_with_different_payload_is_rejected(): void
    {
        [$organization, $customer, $product] = $this->fixture('SUB-B');
        $service = app(SubscriptionLifecycleService::class);
        $base = ['organization_id' => $organization->id, 'customer_id' => $customer->id, 'product_id' => $product->id,
            'idempotency_key' => 'same-key', 'service_starts_on' => '2026-01-01', 'billing_cycle_months' => 1,
            'currency' => 'PEN', 'recurring_subtotal_minor' => 1000, 'recurring_tax_minor' => 0];
        $service->activate($base);

        $this->expectException(DomainException::class);
        $service->activate([...$base, 'recurring_subtotal_minor' => 2000]);
    }

    public function test_renewal_replay_creates_one_period_and_end_of_period_cancellation_stops_future_renewal(): void
    {
        [$organization, $customer, $product] = $this->fixture('SUB-C');
        $service = app(SubscriptionLifecycleService::class);
        $subscription = $service->activate(['organization_id' => $organization->id, 'customer_id' => $customer->id, 'product_id' => $product->id,
            'idempotency_key' => 'sub-c', 'service_starts_on' => '2026-01-01', 'billing_cycle_months' => 1,
            'currency' => 'PEN', 'recurring_subtotal_minor' => 3100, 'recurring_tax_minor' => 0]);

        $first = $service->renew($subscription, 'sub-c-renew-1');
        $replay = $service->renew($subscription->fresh(), 'sub-c-renew-1');
        $this->assertSame($first->id, $replay->id);
        $this->assertDatabaseCount('subscription_service_periods', 2);

        $cancelled = $service->cancel($subscription->fresh(), false, 'Solicitud del cliente');
        $this->assertTrue($cancelled->cancel_at_period_end);
        $this->assertSame(SubscriptionStatus::Active, $cancelled->status);
        $this->assertSame(1, $service->finalizeDue($cancelled->ends_on->toDateString(), $organization->id));
        $this->assertSame(SubscriptionStatus::Ended, $cancelled->fresh()->status);
        $this->expectException(DomainException::class);
        $service->renew($cancelled->fresh(), 'sub-c-renew-2');
    }

    public function test_unbilled_schedule_is_not_claimed_and_adjustment_never_mutates_original(): void
    {
        [$organization, $customer, $product] = $this->fixture('SUB-D');
        $lifecycle = app(SubscriptionLifecycleService::class);
        $subscription = $lifecycle->activate(['organization_id' => $organization->id, 'customer_id' => $customer->id, 'product_id' => $product->id,
            'idempotency_key' => 'sub-d', 'service_starts_on' => '2026-01-01', 'billing_cycle_months' => 1,
            'currency' => 'PEN', 'recurring_subtotal_minor' => 3100, 'recurring_tax_minor' => 0]);
        $accrual = $subscription->accruals()->firstOrFail();
        $ids = app(SubscriptionAccrualService::class)->claimDue('2026-01-31', 10, $organization->id);
        $this->assertSame([], $ids);

        $this->assertSame(3100, SubscriptionAccrualSchedule::query()->findOrFail($accrual->id)->amount_minor);
        $this->assertDatabaseCount('accounting_economic_events', 0);
        $this->expectException(DomainException::class);
        $lifecycle->adjust($subscription, -100, CarbonImmutable::parse('2026-02-01'), 'Sin ingreso devengado', 'sub-d-adjust-1');
    }

    public function test_cross_tenant_customer_is_rejected(): void
    {
        [$organization, , $product] = $this->fixture('SUB-E');
        [, $foreignCustomer] = $this->fixture('SUB-F');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(SubscriptionLifecycleService::class)->activate(['organization_id' => $organization->id, 'customer_id' => $foreignCustomer->id, 'product_id' => $product->id,
            'idempotency_key' => 'cross-tenant', 'service_starts_on' => '2026-01-01', 'billing_cycle_months' => 1,
            'currency' => 'PEN', 'recurring_subtotal_minor' => 1000, 'recurring_tax_minor' => 0]);
    }

    /** @return array{Organization,User,Product} */
    private function fixture(string $code): array
    {
        $organization = Organization::query()->create(['code' => $code, 'name' => $code, 'slug' => strtolower($code), 'status' => 'active', 'environment' => 'demo', 'is_default' => false, 'settings_json' => []]);
        app(OrganizationEntitlementService::class)->assignPlan($organization, SaasPlan::query()->where('code', 'legacy_full')->firstOrFail());
        AccountingSetting::query()->create(['organization_id' => $organization->id, 'fiscal_year' => 2026, 'default_currency' => 'PEN']);
        $customer = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $category = Category::query()->create(['organization_id' => $organization->id, 'name' => 'Category '.$code, 'slug' => strtolower($code).'-category', 'accounting_treatment' => ProductAccountingTreatment::Inherit]);
        $product = Product::query()->create(['organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Subscription '.$code,
            'sku' => $code.'-SUB', 'slug' => strtolower($code).'-sub', 'tax_affectation' => 'Gravado', 'product_type' => ProductType::Subscription,
            'accounting_treatment' => ProductAccountingTreatment::Inherit, 'price' => 10, 'is_active' => true]);

        return [$organization, $customer, $product];
    }
}

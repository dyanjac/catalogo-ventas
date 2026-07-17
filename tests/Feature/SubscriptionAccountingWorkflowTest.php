<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Accounting\Services\EconomicEventService;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Commerce\Entities\SaasPlan;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Orders\Entities\Order;
use Modules\Subscriptions\Services\SubscriptionAccrualService;
use Modules\Subscriptions\Services\SubscriptionBillingLinkService;
use Modules\Subscriptions\Services\SubscriptionLifecycleService;
use Tests\TestCase;

class SubscriptionAccountingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_advance_invoice_credits_deferred_revenue_and_accrual_releases_it(): void
    {
        [$organization, $customer, $product] = $this->fixture();
        $order = Order::query()->create([
            'organization_id' => $organization->id, 'user_id' => $customer->id, 'series' => 'SUB', 'order_number' => 1,
            'status' => 'fulfilled', 'currency' => 'PEN', 'subtotal' => 100, 'tax' => 18, 'total' => 118,
            'shipping_address' => [], 'payment_method' => 'cash', 'payment_status' => 'paid', 'paid_at' => now(),
        ]);
        $item = $order->items()->create([
            'organization_id' => $organization->id, 'product_id' => $product->id, 'currency' => 'PEN', 'quantity' => 1,
            'unit_price' => 118, 'discount_amount' => 0, 'tax_amount' => 18, 'line_total' => 118,
        ]);
        $invoice = BillingDocument::query()->create([
            'organization_id' => $organization->id, 'order_id' => $order->id, 'provider' => 'demo',
            'document_type' => 'factura', 'series' => 'F001', 'number' => '1', 'issue_date' => '2026-01-01',
            'subtotal' => 100, 'tax' => 18, 'total' => 118, 'currency' => 'PEN', 'status' => 'issued', 'issued_at' => '2026-01-01 10:00:00',
        ]);
        $events = app(EconomicEventService::class);
        $invoiceEvent = $events->recordInvoice($order->fresh('items'), $invoice);
        $events->process($organization->id, $invoiceEvent->id);

        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $invoiceEvent->fresh()->processed_entry_id, 'account_code' => '701101', 'credit' => 100]);

        $subscription = app(SubscriptionLifecycleService::class)->activate([
            'organization_id' => $organization->id, 'customer_id' => $customer->id, 'product_id' => $product->id,
            'source_order_item_id' => $item->id, 'idempotency_key' => 'accounting-subscription',
            'service_starts_on' => '2026-01-01', 'billing_cycle_months' => 1, 'currency' => 'PEN',
            'recurring_subtotal_minor' => 10_000, 'recurring_tax_minor' => 1_800,
        ]);
        app(SubscriptionBillingLinkService::class)->attach($subscription->periods()->firstOrFail(), $invoice);
        $deferredEvent = AccountingEconomicEvent::query()->where('event_type', EconomicEventType::SubscriptionDeferred->value)->firstOrFail();
        $events->process($organization->id, $deferredEvent->id);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $deferredEvent->fresh()->processed_entry_id, 'account_code' => '701101', 'debit' => 100]);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $deferredEvent->fresh()->processed_entry_id, 'account_code' => '496101', 'credit' => 100]);
        $schedule = $subscription->accruals()->firstOrFail();
        $schedule->forceFill(['status' => 'claimed', 'claimed_at' => now('UTC')->subMinutes(30), 'lease_token' => fake()->uuid()])->save();
        $this->assertSame([$schedule->id], app(SubscriptionAccrualService::class)->claimDue('2026-01-31', 10, $organization->id));
        $schedule = app(SubscriptionAccrualService::class)->process($organization->id, $schedule->id);
        $events->process($organization->id, $schedule->accounting_economic_event_id);
        $entryId = $schedule->economicEvent()->firstOrFail()->processed_entry_id;

        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $entryId, 'account_code' => '496101', 'debit' => 100]);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $entryId, 'account_code' => '701101', 'credit' => 100]);
        $adjustment = app(SubscriptionLifecycleService::class)->adjust($subscription, -100, \Carbon\CarbonImmutable::parse('2026-02-01'), 'CorrecciÃ³n posterior al devengo', 'accounting-adjust-1');
        $this->assertSame(-100, $adjustment->amount_minor);

        try {
            $schedule->forceFill(['amount_minor' => 1])->save();
            $this->fail('Eloquent debiÃ³ rechazar la mutaciÃ³n.');
        } catch (\LogicException) {
            $this->assertSame(10_000, $schedule->fresh()->amount_minor);
        }
        $this->expectException(\Illuminate\Database\QueryException::class);
        \Illuminate\Support\Facades\DB::table('subscription_accrual_schedules')->where('id', $schedule->id)->update(['amount_minor' => 1]);
    }

    /** @return array{Organization,User,Product} */
    private function fixture(): array
    {
        $organization = Organization::query()->create(['code' => 'SUB-ACCOUNT', 'name' => 'Subscription Accounting', 'slug' => 'sub-account', 'status' => 'active', 'environment' => 'demo', 'is_default' => false, 'settings_json' => []]);
        app(OrganizationEntitlementService::class)->assignPlan($organization, SaasPlan::query()->where('code', 'legacy_full')->firstOrFail());
        foreach ([['121201', 'Clientes', 'activo'], ['401111', 'IGV', 'pasivo'], ['496101', 'Ingresos diferidos', 'pasivo'], ['701101', 'Ingresos', 'ingreso']] as [$code, $name, $type]) {
            AccountingAccount::query()->create(['organization_id' => $organization->id, 'code' => $code, 'name' => $name, 'type' => $type, 'level' => 1, 'is_active' => true]);
        }
        AccountingSetting::query()->create([
            'organization_id' => $organization->id, 'fiscal_year' => 2026, 'auto_post_entries' => false,
            'product_accounting_treatment' => ProductAccountingTreatment::Automatic,
            'default_account_receivable' => '121201', 'default_account_tax' => '401111',
            'default_account_deferred_revenue' => '496101', 'default_account_revenue' => '701101',
        ]);
        $customer = User::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $category = Category::query()->create(['organization_id' => $organization->id, 'name' => 'SaaS', 'slug' => 'saas', 'accounting_treatment' => ProductAccountingTreatment::Inherit]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Plan anual', 'sku' => 'SAAS-01', 'slug' => 'plan-anual',
            'tax_affectation' => 'Gravado', 'product_type' => ProductType::Subscription, 'accounting_treatment' => ProductAccountingTreatment::Inherit,
            'price' => 118, 'is_active' => true,
        ]);

        return [$organization, $customer, $product];
    }
}

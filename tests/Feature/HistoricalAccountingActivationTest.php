<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Database\QueryException;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Models\AccountingEntry;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Accounting\Services\HistoricalAccountingActivationService;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Commerce\Entities\SaasPlan;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Orders\Entities\Order;
use Tests\TestCase;

final class HistoricalAccountingActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_persists_a_sealed_report_without_financial_writes_or_jobs(): void
    {
        Queue::fake();
        [$organization] = $this->fixture('HIST-A');

        $run = app(HistoricalAccountingActivationService::class)->simulate($organization->id, now()->subDay()->toDateString());

        $this->assertSame('simulated', $run->status);
        $this->assertSame(2, $run->eligible_count);
        $this->assertSame(0, $run->error_count);
        $this->assertNotSame(str_repeat('0', 64), $run->simulation_hash);
        $this->assertDatabaseCount('accounting_activation_items', 2);
        $this->assertDatabaseCount('accounting_economic_events', 0);
        $this->assertDatabaseCount('accounting_entries', 0);
        Queue::assertNothingPushed();
    }

    public function test_missing_payment_date_blocks_the_run_and_never_uses_now_as_fallback(): void
    {
        Queue::fake();
        [$organization, $order] = $this->fixture('HIST-B');
        $order->forceFill(['paid_at' => null])->save();

        $run = app(HistoricalAccountingActivationService::class)->simulate($organization->id, now()->subDay()->toDateString());

        $this->assertSame('blocked', $run->status);
        $this->assertSame(1, $run->error_count);
        $payment = $run->items->firstWhere('event_type', EconomicEventType::PaymentReceived->value);
        $this->assertNull($payment->occurred_at);
        $this->assertSame('missing_economic_date', $payment->issues[0]['code']);
        $this->assertDatabaseCount('accounting_economic_events', 0);
        Queue::assertNothingPushed();
    }

    public function test_confirmed_snapshot_processes_in_dependency_order_and_is_idempotent(): void
    {
        [$organization] = $this->fixture('HIST-C');
        $service = app(HistoricalAccountingActivationService::class);
        $run = $service->simulate($organization->id, now()->subDay()->toDateString());

        $run = $service->confirm($run, 'CONFIRMAR '.$run->confirmation_token, $run->simulation_hash, null);
        $completed = $service->process($run);

        $this->assertSame('completed', $completed->status);
        $this->assertSame(2, $completed->processed_count);
        $this->assertDatabaseCount('accounting_economic_events', 2);
        $this->assertDatabaseCount('accounting_entries', 2);
        $this->assertDatabaseHas('accounting_economic_events', ['organization_id' => $organization->id, 'event_type' => EconomicEventType::InvoiceIssued->value]);
        $this->assertDatabaseHas('accounting_economic_events', ['organization_id' => $organization->id, 'event_type' => EconomicEventType::PaymentReceived->value]);

        $again = $service->process($completed);
        $this->assertSame('completed', $again->status);
        $this->assertDatabaseCount('accounting_economic_events', 2);
        $this->assertDatabaseCount('accounting_entries', 2);
    }

    public function test_source_drift_after_confirmation_rolls_back_the_whole_batch(): void
    {
        [$organization, $order] = $this->fixture('HIST-D');
        $service = app(HistoricalAccountingActivationService::class);
        $run = $service->simulate($organization->id, now()->subDay()->toDateString());
        $run = $service->confirm($run, 'CONFIRMAR '.$run->confirmation_token, $run->simulation_hash, null);
        $order->forceFill(['transaction_id' => 'TX-CHANGED-AFTER-SIMULATION'])->save();

        try {
            $service->process($run);
            $this->fail('La deriva de fuente debió bloquear la publicación.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('cambió', $exception->getMessage());
        }

        $this->assertSame('failed', $run->fresh()->status);
        $this->assertDatabaseCount('accounting_economic_events', 0);
        $this->assertDatabaseCount('accounting_entries', 0);
    }

    public function test_configuration_drift_invalidates_confirmation(): void
    {
        [$organization] = $this->fixture('HIST-E');
        $service = app(HistoricalAccountingActivationService::class);
        $run = $service->simulate($organization->id, now()->subDay()->toDateString());
        AccountingAccount::query()->where('organization_id', $organization->id)->where('code', '701101')->update(['is_active' => false]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('configuración');
        $service->confirm($run, 'CONFIRMAR '.$run->confirmation_token, $run->simulation_hash, null);
    }

    public function test_scan_is_strictly_isolated_by_organization(): void
    {
        [$organization] = $this->fixture('HIST-F');
        [$other] = $this->fixture('HIST-G');

        $run = app(HistoricalAccountingActivationService::class)->simulate($organization->id, now()->subDay()->toDateString());

        $this->assertSame(2, $run->items->count());
        $this->assertTrue($run->items->every(fn ($item) => $item->organization_id === $organization->id));
        $this->assertFalse($run->items->contains(fn ($item) => $item->organization_id === $other->id));
    }

    public function test_payment_is_blocked_when_its_invoice_is_before_the_cutoff_without_processed_event(): void
    {
        [$organization, , $document] = $this->fixture('HIST-H');
        $document->forceFill(['issued_at' => now()->subDays(2), 'issue_date' => now()->subDays(2)->toDateString()])->save();

        $run = app(HistoricalAccountingActivationService::class)->simulate($organization->id, now()->startOfDay()->toDateString());

        $this->assertSame('blocked', $run->status);
        $payment = $run->items->firstWhere('event_type', EconomicEventType::PaymentReceived->value);
        $this->assertSame('inconsistent', $payment->status);
        $this->assertContains('missing_accounting_dependency', collect($payment->issues)->pluck('code')->all());
        $this->assertDatabaseCount('accounting_economic_events', 0);
    }

    public function test_retrodated_source_added_after_simulation_invalidates_manifest(): void
    {
        [$organization, $order] = $this->fixture('HIST-I');
        $service = app(HistoricalAccountingActivationService::class);
        $run = $service->simulate($organization->id, now()->subDay()->toDateString());
        BillingDocument::query()->create([
            'organization_id' => $organization->id, 'order_id' => $order->id, 'provider' => 'demo',
            'document_type' => 'boleta', 'series' => 'B001', 'number' => '2', 'issue_date' => now()->toDateString(),
            'subtotal' => 100, 'tax' => 18, 'total' => 118, 'currency' => 'PEN', 'status' => 'issued', 'issued_at' => now()->subMinute(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('manifiesto');
        $service->confirm($run, 'CONFIRMAR '.$run->confirmation_token, $run->simulation_hash, null);
    }

    public function test_database_rejects_cross_tenant_activation_item_links(): void
    {
        [$organization] = $this->fixture('HIST-J');
        [$other] = $this->fixture('HIST-K');
        $run = app(HistoricalAccountingActivationService::class)->simulate($organization->id, now()->subDay()->toDateString());

        $this->expectException(QueryException::class);
        DB::table('accounting_activation_items')->where('id', $run->items->first()->id)
            ->update(['organization_id' => $other->id]);
    }

    public function test_database_rejects_mutating_a_sealed_candidate_payload(): void
    {
        [$organization] = $this->fixture('HIST-M');
        $run = app(HistoricalAccountingActivationService::class)->simulate($organization->id, now()->subDay()->toDateString());

        $this->expectException(QueryException::class);
        DB::table('accounting_activation_items')->where('id', $run->items->first()->id)
            ->update(['payload' => json_encode(['tampered' => true], JSON_THROW_ON_ERROR)]);
    }

    public function test_invalid_process_attempt_does_not_mutate_a_blocked_run(): void
    {
        [$organization, $order] = $this->fixture('HIST-L');
        $order->forceFill(['paid_at' => null])->save();
        $service = app(HistoricalAccountingActivationService::class);
        $run = $service->simulate($organization->id, now()->subDay()->toDateString());

        try {
            $service->process($run);
            $this->fail('Una corrida bloqueada no puede procesarse.');
        } catch (DomainException) {
            $this->assertSame('blocked', $run->fresh()->status);
        }
    }

    /** @return array{Organization,Order,BillingDocument} */
    private function fixture(string $code): array
    {
        $organization = Organization::query()->create([
            'code' => $code, 'name' => 'Organization '.$code, 'slug' => strtolower($code),
            'status' => 'active', 'environment' => 'demo', 'is_default' => false, 'settings_json' => [],
        ]);
        app(OrganizationEntitlementService::class)->assignPlan($organization, SaasPlan::query()->where('code', 'legacy_full')->firstOrFail());
        foreach ([
            ['101101', 'Caja', 'activo'], ['121201', 'Clientes', 'activo'], ['201101', 'Mercaderías', 'activo'],
            ['401111', 'IGV', 'pasivo'], ['691101', 'Costo de venta', 'gasto'], ['701101', 'Ventas', 'ingreso'],
        ] as [$accountCode, $name, $type]) {
            AccountingAccount::query()->create(['organization_id' => $organization->id, 'code' => $accountCode, 'name' => $name, 'type' => $type, 'level' => 1, 'is_active' => true]);
        }
        AccountingSetting::query()->create([
            'organization_id' => $organization->id, 'fiscal_year' => now()->year,
            'default_currency' => 'PEN', 'auto_post_entries' => true,
            'product_accounting_treatment' => ProductAccountingTreatment::Automatic,
            'default_account_cash' => '101101', 'default_account_receivable' => '121201',
            'default_account_inventory' => '201101', 'default_account_tax' => '401111',
            'default_account_cogs' => '691101', 'default_account_revenue' => '701101',
        ]);
        AccountingPeriod::query()->create([
            'organization_id' => $organization->id, 'year' => now()->year, 'month' => now()->month,
            'starts_at' => now()->startOfMonth()->toDateString(), 'ends_at' => now()->endOfMonth()->toDateString(), 'status' => 'open',
        ]);
        $category = Category::query()->create(['organization_id' => $organization->id, 'name' => 'Cat '.$code, 'slug' => strtolower($code).'-cat', 'accounting_treatment' => ProductAccountingTreatment::Inherit]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Product '.$code,
            'sku' => $code.'-SKU', 'slug' => strtolower($code).'-product', 'tax_affectation' => 'Gravado',
            'product_type' => ProductType::PhysicalGood, 'accounting_treatment' => ProductAccountingTreatment::Inherit,
            'price' => 118, 'is_active' => true,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $order = Order::query()->create([
            'organization_id' => $organization->id, 'user_id' => $user->id, 'series' => 'PED', 'order_number' => 1,
            'status' => 'fulfilled', 'currency' => 'PEN', 'subtotal' => 100, 'tax' => 18, 'total' => 118,
            'shipping_address' => [], 'payment_method' => 'cash', 'payment_status' => 'paid', 'paid_at' => now(),
        ]);
        $order->items()->create([
            'organization_id' => $organization->id, 'product_id' => $product->id, 'currency' => 'PEN',
            'quantity' => 1, 'unit_price' => 118, 'discount_amount' => 0, 'tax_amount' => 18, 'line_total' => 118,
        ]);
        $document = BillingDocument::query()->create([
            'organization_id' => $organization->id, 'order_id' => $order->id, 'provider' => 'demo',
            'document_type' => 'factura', 'series' => 'F001', 'number' => '1', 'issue_date' => now()->toDateString(),
            'subtotal' => 100, 'tax' => 18, 'total' => 118, 'currency' => 'PEN', 'status' => 'issued', 'issued_at' => now(),
        ]);

        return [$organization, $order->fresh(['items', 'billingDocuments']), $document];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Exceptions\EconomicEventConflictException;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Models\AccountingPeriod;
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
use RuntimeException;
use Tests\TestCase;

class AccountingEconomicEventWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_event_is_idempotent_balanced_and_immutable(): void
    {
        [$organization, $order] = $this->fixture('EVENT-A');
        $events = app(EconomicEventService::class);

        $event = $events->recordOrderSale($order);
        $event->refresh()->load('entry.lines');

        $this->assertSame(EconomicEventStatus::Processed, $event->status);
        $this->assertSame('118.00', $event->entry->total_debit);
        $this->assertSame('118.00', $event->entry->total_credit);
        $this->assertSame(1, AccountingEconomicEvent::query()->where('organization_id', $organization->id)->count());
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $event->entry->id, 'account_code' => '121201', 'debit' => 118]);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $event->entry->id, 'account_code' => '701101', 'credit' => 100]);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $event->entry->id, 'account_code' => '401111', 'credit' => 18]);

        $replay = $events->recordOrderSale($order->fresh());
        $this->assertSame($event->id, $replay->id);
        $this->assertDatabaseCount('accounting_entries', 1);

        $this->expectException(LogicException::class);
        $event->entry->update(['description' => 'mutado']);
    }

    public function test_same_idempotency_key_with_a_different_payload_is_rejected(): void
    {
        [$organization, $order] = $this->fixture('EVENT-B');
        $events = app(EconomicEventService::class);
        $events->recordOrderSale($order);

        $this->expectException(EconomicEventConflictException::class);
        $events->record(
            (int) $organization->id,
            EconomicEventType::InvoiceIssued,
            "order:{$order->id}:sale-issued",
            Order::class,
            (int) $order->id,
            'CONFLICT',
            ['order_id' => $order->id, 'total' => '999.00'],
        );
    }

    public function test_payment_and_reversal_create_linked_mirror_entries(): void
    {
        [, $order] = $this->fixture('EVENT-C');
        $events = app(EconomicEventService::class);
        $events->recordOrderSale($order);

        $payment = $events->recordPayment($order);
        $payment->refresh()->load('entry.lines');
        $this->assertSame(EconomicEventStatus::Processed, $payment->status);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $payment->entry->id, 'account_code' => '101101', 'debit' => 118]);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $payment->entry->id, 'account_code' => '121201', 'credit' => 118]);

        $reversal = $events->reverse($payment, 'payment-event:'.$payment->id.':reversal');
        $reversal->refresh()->load('entry.lines');
        $payment->refresh();

        $this->assertSame(EconomicEventStatus::Processed, $reversal->status);
        $this->assertSame(EconomicEventStatus::Reversed, $payment->status);
        $this->assertSame($payment->entry->id, $reversal->entry->reversal_of_id);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $reversal->entry->id, 'account_code' => '101101', 'credit' => 118]);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $reversal->entry->id, 'account_code' => '121201', 'debit' => 118]);
    }

    public function test_closed_period_keeps_event_with_retriable_error_and_no_partial_entry(): void
    {
        [$organization, $order] = $this->fixture('EVENT-D');
        AccountingPeriod::query()->create([
            'organization_id' => $organization->id,
            'year' => now()->year,
            'month' => now()->month,
            'starts_at' => now()->startOfMonth()->toDateString(),
            'ends_at' => now()->endOfMonth()->toDateString(),
            'status' => 'closed',
        ]);

        $event = app(EconomicEventService::class)->recordOrderSale($order);
        $event->refresh();

        $this->assertSame(EconomicEventStatus::Error, $event->status);
        $this->assertStringContainsString('cerrado', (string) $event->error_message);
        $this->assertNull($event->processed_entry_id);
        $this->assertDatabaseCount('accounting_entries', 0);
    }

    public function test_cost_sale_return_and_credit_note_use_compensating_entries(): void
    {
        [$organization, $order] = $this->fixture('EVENT-E');
        $events = app(EconomicEventService::class);
        $productId = (int) $order->items->first()->product_id;

        $dispatch = $events->record(
            (int) $organization->id,
            EconomicEventType::InventoryDispatched,
            'dispatch:500:accounting',
            Order::class,
            500,
            'DESP-500',
            ['order_id' => $order->id, 'items' => [['product_id' => $productId, 'total_cost' => '60.00']]],
        );
        $return = $events->record(
            (int) $organization->id,
            EconomicEventType::InventoryReturned,
            'return:501:accounting',
            Order::class,
            501,
            'DEV-501',
            ['order_id' => $order->id, 'items' => [['product_id' => $productId, 'total_cost' => '60.00']]],
        );
        $dispatch->refresh();
        $return->refresh();
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $dispatch->processed_entry_id, 'account_code' => '691101', 'debit' => 60]);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $dispatch->processed_entry_id, 'account_code' => '201101', 'credit' => 60]);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $return->processed_entry_id, 'account_code' => '201101', 'debit' => 60]);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $return->processed_entry_id, 'account_code' => '691101', 'credit' => 60]);

        $invoice = BillingDocument::query()->create([
            'organization_id' => $organization->id, 'order_id' => $order->id, 'provider' => 'demo',
            'document_type' => 'factura', 'series' => 'F001', 'number' => '1', 'issue_date' => now()->toDateString(),
            'subtotal' => 100, 'tax' => 18, 'total' => 118, 'currency' => 'PEN', 'status' => 'issued', 'issued_at' => now(),
        ]);
        $invoiceEvent = $events->recordInvoice($order, $invoice);
        $creditNote = BillingDocument::query()->create([
            'organization_id' => $organization->id, 'order_id' => $order->id, 'related_document_id' => $invoice->id,
            'provider' => 'demo', 'document_type' => 'credit_note', 'series' => 'FC01', 'number' => '1',
            'issue_date' => now()->toDateString(), 'subtotal' => 50, 'tax' => 9, 'total' => 59,
            'currency' => 'PEN', 'status' => 'issued', 'issued_at' => now(),
        ]);
        $creditEvent = $events->recordCreditNote($order, $creditNote);
        $invoiceEvent->refresh();
        $creditEvent->refresh();

        $this->assertNotNull($invoiceEvent->processed_entry_id);
        $this->assertSame('59.00', $creditEvent->entry->total_debit);
        $this->assertSame('59.00', $creditEvent->entry->total_credit);
        $this->assertDatabaseHas('accounting_entry_lines', ['accounting_entry_id' => $creditEvent->processed_entry_id, 'account_code' => '121201', 'credit' => 59]);
    }

    public function test_economic_fact_and_outbox_event_rollback_together(): void
    {
        [$organization, $order] = $this->fixture('EVENT-F');
        $order->forceFill(['payment_status' => 'pending', 'paid_at' => null])->save();

        try {
            DB::transaction(function () use ($order): void {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                $locked->forceFill(['payment_status' => 'paid', 'paid_at' => now()])->save();
                app(EconomicEventService::class)->recordPayment($locked->fresh('items'));
                throw new RuntimeException('forced rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('forced rollback', $exception->getMessage());
        }

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertDatabaseMissing('accounting_economic_events', [
            'organization_id' => $organization->id,
            'event_type' => EconomicEventType::PaymentReceived->value,
        ]);

        DB::transaction(function () use ($order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->forceFill(['payment_status' => 'paid', 'paid_at' => now()])->save();
            app(EconomicEventService::class)->recordPayment($locked->fresh('items'));
        });

        $this->assertDatabaseHas('accounting_economic_events', [
            'organization_id' => $organization->id,
            'event_type' => EconomicEventType::PaymentReceived->value,
            'status' => EconomicEventStatus::Processed->value,
        ]);
    }

    /** @return array{Organization,Order} */
    private function fixture(string $code): array
    {
        $organization = Organization::query()->create([
            'code' => $code,
            'name' => 'Organization '.$code,
            'slug' => strtolower($code),
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => false,
            'settings_json' => [],
        ]);
        app(OrganizationEntitlementService::class)->assignPlan($organization, SaasPlan::query()->where('code', 'legacy_full')->firstOrFail());
        foreach ([
            ['101101', 'Caja', 'activo'], ['121201', 'Clientes', 'activo'], ['201101', 'Mercaderías', 'activo'],
            ['401111', 'IGV', 'pasivo'], ['691101', 'Costo de venta', 'gasto'], ['701101', 'Ventas', 'ingreso'],
        ] as [$accountCode, $name, $type]) {
            AccountingAccount::query()->create(['organization_id' => $organization->id, 'code' => $accountCode, 'name' => $name, 'type' => $type, 'level' => 1, 'is_active' => true]);
        }
        AccountingSetting::query()->create([
            'organization_id' => $organization->id,
            'fiscal_year' => now()->year,
            'auto_post_entries' => true,
            'product_accounting_treatment' => ProductAccountingTreatment::Automatic,
            'default_account_cash' => '101101',
            'default_account_receivable' => '121201',
            'default_account_inventory' => '201101',
            'default_account_tax' => '401111',
            'default_account_cogs' => '691101',
            'default_account_revenue' => '701101',
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
            'organization_id' => $organization->id, 'product_id' => $product->id, 'currency' => 'PEN', 'quantity' => 1,
            'unit_price' => 118, 'discount_amount' => 0, 'tax_amount' => 18, 'line_total' => 118,
        ]);

        return [$organization, $order->fresh('items')];
    }
}

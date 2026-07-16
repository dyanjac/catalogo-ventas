<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Models\AccountingEntry;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Accounting\Services\SalesAccountingService;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Commerce\Entities\SaasPlan;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Orders\Entities\Order;
use Tests\TestCase;

class SalesAccountingTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_autopost_uses_the_order_tenant_instead_of_the_default_context(): void
    {
        $defaultOrganization = Organization::query()->where('is_default', true)->firstOrFail();
        $orderOrganization = $this->createOrganization('ORDER-TENANT');
        app(OrganizationEntitlementService::class)->assignPlan(
            $orderOrganization,
            SaasPlan::query()->where('code', 'legacy_full')->firstOrFail()
        );

        AccountingSetting::query()->create([
            'organization_id' => $orderOrganization->id,
            'fiscal_year' => 2026,
            'auto_post_entries' => true,
            'product_accounting_treatment' => ProductAccountingTreatment::Automatic->value,
            'default_account_revenue' => '701-B',
            'default_account_receivable' => '121-B',
            'default_account_tax' => '401-B',
        ]);
        $this->createAccount($orderOrganization, '701-B', 'Ventas B', 'ingreso');
        $this->createAccount($orderOrganization, '121-B', 'Clientes B', 'activo');
        $this->createAccount($orderOrganization, '401-B', 'IGV B', 'pasivo');

        $category = Category::query()->create([
            'organization_id' => $orderOrganization->id,
            'name' => 'Products B',
            'slug' => 'products-b',
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $product = Product::query()->create([
            'organization_id' => $orderOrganization->id,
            'category_id' => $category->id,
            'name' => 'Product B',
            'sku' => 'PRODUCT-B',
            'slug' => 'product-b',
            'tax_affectation' => 'Gravado',
            'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 118,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'organization_id' => $orderOrganization->id,
            'email' => 'worker-order-tenant@example.test',
        ]);
        $order = Order::query()->create([
            'organization_id' => $orderOrganization->id,
            'user_id' => $user->id,
            'series' => 'PED',
            'order_number' => 1,
            'status' => 'paid',
            'currency' => 'PEN',
            'subtotal' => 100,
            'tax' => 18,
            'total' => 118,
            'shipping_address' => [],
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'currency' => 'PEN',
            'quantity' => 1,
            'unit_price' => 118,
            'discount_amount' => 0,
            'tax_amount' => 18,
            'line_total' => 118,
        ]);

        $result = app(SalesAccountingService::class)->postIssuedSale($order->fresh());

        $this->assertTrue($result['created']);
        $this->assertDatabaseHas('accounting_entries', [
            'id' => $result['entry_id'],
            'organization_id' => $orderOrganization->id,
            'reference' => 'VENTA-ORDER-'.$order->id,
        ]);
        $this->assertDatabaseMissing('accounting_entries', [
            'organization_id' => $defaultOrganization->id,
            'reference' => 'VENTA-ORDER-'.$order->id,
        ]);
        $this->assertSame(
            [$orderOrganization->id],
            AccountingEntry::query()->findOrFail($result['entry_id'])->lines()->pluck('organization_id')->unique()->values()->all()
        );

        $foreignCategory = Category::query()->create([
            'organization_id' => $defaultOrganization->id,
            'name' => 'Foreign products',
            'slug' => 'foreign-products',
            'accounting_treatment' => ProductAccountingTreatment::Automatic->value,
        ]);
        $foreignProduct = Product::query()->create([
            'organization_id' => $defaultOrganization->id,
            'category_id' => $foreignCategory->id,
            'name' => 'Foreign product',
            'sku' => 'FOREIGN-PRODUCT',
            'slug' => 'foreign-product',
            'tax_affectation' => 'Gravado',
            'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Automatic->value,
            'account_revenue' => '701-B',
            'account_receivable' => '121-B',
            'account_tax' => '401-B',
            'price' => 118,
            'is_active' => true,
        ]);
        $foreignOrder = Order::query()->create([
            'organization_id' => $orderOrganization->id,
            'user_id' => $user->id,
            'series' => 'PED',
            'order_number' => 2,
            'status' => 'paid',
            'currency' => 'PEN',
            'subtotal' => 100,
            'tax' => 18,
            'total' => 118,
            'shipping_address' => [],
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $foreignOrder->items()->create([
            'product_id' => $foreignProduct->id,
            'currency' => 'PEN',
            'quantity' => 1,
            'unit_price' => 118,
            'discount_amount' => 0,
            'tax_amount' => 18,
            'line_total' => 118,
        ]);

        $foreignResult = app(SalesAccountingService::class)->postIssuedSale($foreignOrder->fresh());

        $this->assertFalse($foreignResult['created']);
        $this->assertDatabaseMissing('accounting_entries', [
            'organization_id' => $orderOrganization->id,
            'reference' => 'VENTA-ORDER-'.$foreignOrder->id,
        ]);
    }

    private function createOrganization(string $code, bool $default = false): Organization
    {
        return Organization::query()->create([
            'code' => $code,
            'name' => 'Organization '.$code,
            'slug' => strtolower($code),
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => $default,
            'settings_json' => [],
        ]);
    }

    private function createAccount(Organization $organization, string $code, string $name, string $type): AccountingAccount
    {
        return AccountingAccount::query()->create([
            'organization_id' => $organization->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'level' => 1,
            'is_active' => true,
        ]);
    }
}

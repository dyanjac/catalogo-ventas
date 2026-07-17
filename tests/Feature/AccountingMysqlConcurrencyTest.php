<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Models\AccountingEntry;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Commerce\Entities\SaasPlan;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Orders\Entities\Order;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AccountingMysqlConcurrencyTest extends TestCase
{
    public function test_mysql_serializes_event_capture_and_posting(): void
    {
        if (DB::getDriverName() !== 'mysql' || getenv('PHASE08_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Prueba opt-in exclusiva para MySQL/InnoDB.');
        }

        [$organization, $order, $product] = $this->scope();
        $processes = collect([0, 1])->map(function () use ($organization, $order, $product): Process {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/phase08_accounting_worker.php'),
                (string) $organization->id,
                (string) $order->id,
                (string) $product->id,
                'phase08-mysql-same-event',
            ], base_path(), null, null, 90);
            $process->start();

            return $process;
        });
        $results = $processes->map(function (Process $process): array {
            $process->wait();

            return ['exit' => $process->getExitCode(), 'out' => trim($process->getOutput()), 'error' => $process->getErrorOutput()];
        });

        $this->assertSame(2, $results->where('exit', 0)->count(), $results->toJson());
        $this->assertCount(1, $results->pluck('out')->unique());
        $this->assertSame(1, AccountingEconomicEvent::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(1, AccountingEntry::query()->where('organization_id', $organization->id)->count());
    }

    /** @return array{Organization,Order,Product} */
    private function scope(): array
    {
        $suffix = uniqid();
        $organization = Organization::query()->create([
            'code' => 'F8M-'.$suffix, 'name' => 'Phase 08 MySQL', 'slug' => 'phase08-mysql-'.$suffix,
            'status' => 'active', 'environment' => 'demo', 'is_default' => true,
        ]);
        app(OrganizationEntitlementService::class)->assignPlan($organization, SaasPlan::query()->where('code', 'legacy_full')->firstOrFail());
        foreach ([['121', 'Clientes', 'activo'], ['401', 'IGV', 'pasivo'], ['701', 'Ventas', 'ingreso']] as [$code, $name, $type]) {
            AccountingAccount::query()->create(['organization_id' => $organization->id, 'code' => $code, 'name' => $name, 'type' => $type, 'level' => 1, 'is_active' => true]);
        }
        AccountingSetting::query()->create([
            'organization_id' => $organization->id, 'fiscal_year' => now()->year, 'auto_post_entries' => false,
            'product_accounting_treatment' => ProductAccountingTreatment::Automatic,
            'default_account_receivable' => '121', 'default_account_tax' => '401', 'default_account_revenue' => '701',
        ]);
        $category = Category::query()->create([
            'organization_id' => $organization->id, 'name' => 'F8 '.$suffix, 'slug' => 'f8-'.$suffix,
            'accounting_treatment' => ProductAccountingTreatment::Inherit,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Product F8',
            'sku' => 'F8-'.$suffix, 'slug' => 'product-f8-'.$suffix, 'tax_affectation' => 'Gravado',
            'product_type' => ProductType::PhysicalGood, 'accounting_treatment' => ProductAccountingTreatment::Inherit,
            'price' => 118, 'is_active' => true,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $order = Order::query()->create([
            'organization_id' => $organization->id, 'user_id' => $user->id, 'series' => 'F8', 'order_number' => 1,
            'status' => 'fulfilled', 'currency' => 'PEN', 'subtotal' => 100, 'tax' => 18, 'total' => 118,
            'shipping_address' => [], 'payment_method' => 'cash', 'payment_status' => 'paid',
        ]);

        return [$organization, $order, $product];
    }
}

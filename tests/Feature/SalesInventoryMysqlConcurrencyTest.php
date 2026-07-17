<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryReservation;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Services\InventoryLedgerBackfillService;
use Modules\Orders\Entities\Order;
use Modules\Orders\Enums\SalesInventoryChannelMode;
use Modules\Orders\Services\SalesInventoryChannelRolloutService;
use Modules\Security\Models\SecurityBranch;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SalesInventoryMysqlConcurrencyTest extends TestCase
{
    public function test_mysql_serializes_checkout_replays_and_dispatch_confirmation(): void
    {
        if (DB::getDriverName() !== 'mysql' || getenv('PHASE06_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Prueba opt-in exclusiva para MySQL/InnoDB.');
        }

        $scope = $this->scope();
        $competing = $this->runCheckoutWorkers($scope, [
            ['quantity' => 6, 'key' => 'phase06-race-a'],
            ['quantity' => 6, 'key' => 'phase06-race-b'],
        ]);
        $this->assertSame(1, collect($competing)->where('exit', 0)->count(), json_encode($competing));
        $this->assertSame(1, Order::query()->where('organization_id', $scope['organization']->id)->count());
        $this->assertSame(6, $scope['balance']->fresh()->reserved_stock);
        $this->assertSame(10, $scope['balance']->fresh()->physical_stock);

        $sameKey = $this->runCheckoutWorkers($scope, [
            ['quantity' => 2, 'key' => 'phase06-same-key'],
            ['quantity' => 2, 'key' => 'phase06-same-key'],
        ]);
        $this->assertSame(2, collect($sameKey)->where('exit', 0)->count(), json_encode($sameKey));
        $this->assertSame(2, Order::query()->where('organization_id', $scope['organization']->id)->count());
        $this->assertSame(2, InventoryReservation::query()->where('organization_id', $scope['organization']->id)->count());
        $this->assertSame(8, $scope['balance']->fresh()->reserved_stock);

        $orderId = (int) trim((string) $sameKey[0]['output']);
        $dispatches = $this->runDispatchWorkers($scope, $orderId);
        $this->assertSame(2, collect($dispatches)->where('exit', 0)->count(), json_encode($dispatches));
        $order = Order::query()->findOrFail($orderId);
        $this->assertSame('dispatched', $order->warehouse_status->value);
        $this->assertSame(8, $scope['balance']->fresh()->physical_stock);
        $this->assertSame(6, $scope['balance']->fresh()->reserved_stock);
        $this->assertSame(1, InventoryDocument::query()->where('id', $order->dispatch_document_id)->count());
        $this->assertSame(1, InventoryMovement::query()->where('reference_id', $order->dispatch_document_id)->count());

        $posScope = $this->scope('pos');
        $posSales = $this->runPosWorkers($posScope, [
            ['quantity' => 5, 'key' => 'phase06-pos-a'],
            ['quantity' => 5, 'key' => 'phase06-pos-b'],
        ]);
        $this->assertSame(2, collect($posSales)->where('exit', 0)->count(), json_encode($posSales));
        $posOrders = Order::query()->where('organization_id', $posScope['organization']->id)->where('sales_channel', 'pos')->orderBy('id')->get();
        $this->assertCount(2, $posOrders);
        $this->assertCount(2, $posOrders->pluck('order_number')->unique());
        $this->assertSame(10, $posScope['balance']->fresh()->reserved_stock);
        $this->assertSame(10, $posScope['balance']->fresh()->physical_stock);
    }

    /** @param array<int, array{quantity:int,key:string}> $commands @return array<int, array{exit:int,output:string,error:string}> */
    private function runCheckoutWorkers(array $scope, array $commands): array
    {
        $processes = collect($commands)->map(function (array $command) use ($scope): Process {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/phase06_checkout_worker.php'),
                (string) $scope['user']->id,
                (string) $scope['product']->id,
                (string) $command['quantity'],
                $command['key'],
            ], base_path(), null, null, 90);
            $process->start();

            return $process;
        });

        return $processes->map(function (Process $process): array {
            $process->wait();

            return ['exit' => $process->getExitCode() ?? -1, 'output' => $process->getOutput(), 'error' => $process->getErrorOutput()];
        })->all();
    }

    /** @return array<int, array{exit:int,output:string,error:string}> */
    private function runDispatchWorkers(array $scope, int $orderId): array
    {
        $processes = collect([0, 1])->map(function () use ($scope, $orderId): Process {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/phase06_dispatch_worker.php'),
                (string) $scope['user']->id,
                (string) $orderId,
            ], base_path(), null, null, 90);
            $process->start();

            return $process;
        });

        return $processes->map(function (Process $process): array {
            $process->wait();

            return ['exit' => $process->getExitCode() ?? -1, 'output' => $process->getOutput(), 'error' => $process->getErrorOutput()];
        })->all();
    }

    /** @param array<int, array{quantity:int,key:string}> $commands @return array<int, array{exit:int,output:string,error:string}> */
    private function runPosWorkers(array $scope, array $commands): array
    {
        $processes = collect($commands)->map(function (array $command) use ($scope): Process {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/phase06_pos_worker.php'),
                (string) $scope['user']->id,
                (string) $scope['product']->id,
                (string) $command['quantity'],
                $command['key'],
            ], base_path(), null, null, 90);
            $process->start();

            return $process;
        });

        return $processes->map(function (Process $process): array {
            $process->wait();

            return ['exit' => $process->getExitCode() ?? -1, 'output' => $process->getOutput(), 'error' => $process->getErrorOutput()];
        })->all();
    }

    /** @return array<string, mixed> */
    private function scope(string $channel = 'ecommerce'): array
    {
        $suffix = uniqid();
        $organization = Organization::query()->create([
            'code' => 'F6M-'.$suffix, 'name' => 'Phase 06 MySQL', 'slug' => 'phase06-mysql-'.$suffix,
            'status' => 'active', 'environment' => 'demo', 'is_default' => true,
        ]);
        $branch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => 'F6B-'.$suffix, 'name' => 'Principal',
            'is_active' => true, 'is_default' => true,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'branch_id' => $branch->id]);
        $this->actingAs($user);
        $category = Category::query()->create([
            'organization_id' => $organization->id, 'name' => 'Physical', 'slug' => 'physical-'.$suffix,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Flour', 'sku' => 'F6M-'.$suffix,
            'slug' => 'flour-'.$suffix, 'tax_affectation' => 'Gravado', 'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value, 'price' => 10, 'purchase_price' => 2,
            'average_price' => 2, 'stock' => 10, 'min_stock' => 0, 'is_active' => true,
        ]);
        $warehouse = InventoryWarehouse::query()->create([
            'organization_id' => $organization->id, 'branch_id' => $branch->id, 'code' => 'F6W-'.$suffix,
            'name' => 'Principal', 'is_default' => true, 'is_active' => true,
        ]);
        ProductBranchStock::query()->create([
            'organization_id' => $organization->id, 'product_id' => $product->id, 'branch_id' => $branch->id,
            'stock' => 10, 'min_stock' => 0, 'is_active' => true,
        ]);
        ProductWarehouseStock::query()->create([
            'organization_id' => $organization->id, 'product_id' => $product->id, 'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id, 'stock' => 10, 'min_stock' => 0,
            'average_cost' => 2, 'last_cost' => 2, 'is_active' => true,
        ]);
        app(InventoryLedgerBackfillService::class)->run($organization->id);
        InventoryLedgerRollout::query()->create([
            'organization_id' => $organization->id, 'mode' => InventoryLedgerRolloutMode::Active->value,
            'reconciled_at' => now(), 'activated_at' => now(),
        ]);
        app(SalesInventoryChannelRolloutService::class)->setMode($organization->id, $channel, SalesInventoryChannelMode::Active);

        return [
            'organization' => $organization, 'branch' => $branch, 'user' => $user, 'product' => $product,
            'warehouse' => $warehouse,
            'balance' => InventoryBalance::query()->where('warehouse_id', $warehouse->id)->firstOrFail(),
        ];
    }
}

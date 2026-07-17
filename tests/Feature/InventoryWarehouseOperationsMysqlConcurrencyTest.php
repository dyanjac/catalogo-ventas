<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Data\InventoryTransferCommand;
use Modules\Catalog\Data\InventoryTransferItemData;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Enums\InventoryTransferStatus;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Services\InventoryDocumentService;
use Modules\Catalog\Services\InventoryLedgerBackfillService;
use Modules\Catalog\Services\InventoryTransferService;
use Modules\Security\Models\SecurityBranch;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryWarehouseOperationsMysqlConcurrencyTest extends TestCase
{
    public function test_mysql_serializes_partial_receipts_without_over_receiving_or_losing_updates(): void
    {
        if (DB::getDriverName() !== 'mysql' || getenv('PHASE05_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Prueba opt-in exclusiva para MySQL/InnoDB.');
        }

        $scope = $this->scope(40);
        $service = app(InventoryTransferService::class);

        $overReceipt = $service->create($this->transferCommand($scope, 'mysql-over-receipt'));
        $service->dispatch($scope['organization']->id, $overReceipt->id, 'mysql-over-receipt:dispatch', $scope['user']->id);
        $overReceiptItem = $overReceipt->fresh('items')->items->firstOrFail();
        $overReceiptResults = $this->runConcurrentReceipts($scope, $overReceipt, $overReceiptItem->id, 6, 6);

        $this->assertSame(1, collect($overReceiptResults)->where('exit', 0)->count());
        $this->assertSame(6, $overReceiptItem->fresh()->received_quantity);
        $this->assertSame(4, $scope['destination_balance']->fresh()->in_transit_stock);
        $this->assertSame(InventoryTransferStatus::PartiallyReceived, $overReceipt->fresh()->status);

        $exactReceipt = $service->create($this->transferCommand($scope, 'mysql-exact-receipt'));
        $service->dispatch($scope['organization']->id, $exactReceipt->id, 'mysql-exact-receipt:dispatch', $scope['user']->id);
        $exactReceiptItem = $exactReceipt->fresh('items')->items->firstOrFail();
        $exactReceiptResults = $this->runConcurrentReceipts($scope, $exactReceipt, $exactReceiptItem->id, 5, 5);

        $this->assertSame(2, collect($exactReceiptResults)->where('exit', 0)->count());
        $this->assertSame(10, $exactReceiptItem->fresh()->received_quantity);
        $this->assertSame(4, $scope['destination_balance']->fresh()->in_transit_stock);
        $this->assertSame(InventoryTransferStatus::Received, $exactReceipt->fresh()->status);

        $firstDispatch = $service->create($this->transferCommand($scope, 'mysql-dispatch-a', 5));
        $secondDispatch = $service->create($this->transferCommand($scope, 'mysql-dispatch-b', 5));
        $dispatchResults = $this->runConcurrentDispatches($scope, [$firstDispatch, $secondDispatch]);

        $this->assertSame(2, collect($dispatchResults)->where('exit', 0)->count());
        $this->assertSame(14, $scope['destination_balance']->fresh()->in_transit_stock);
        $this->assertSame(10, $scope['source_balance']->fresh()->physical_stock);

        $documents = app(InventoryDocumentService::class);
        $document = $documents->createDraft([
            'organization_id' => $scope['organization']->id,
            'idempotency_key' => 'mysql-reversal-document',
            'document_type' => 'inbound',
            'branch_id' => $scope['source_branch']->id,
            'warehouse_id' => $scope['source_warehouse']->id,
            'created_by' => $scope['user']->id,
            'items' => [['product_id' => $scope['product']->id, 'quantity' => 1, 'unit_cost' => 2]],
        ]);
        $documents->confirm($document->id, $scope['user']->id);
        $reversalResults = $this->runConcurrentReversals($scope, $document);

        $this->assertSame(2, collect($reversalResults)->where('exit', 0)->count());
        $this->assertSame(1, InventoryDocument::query()->where('reversal_of_id', $document->id)->count());
        $this->assertSame(10, $scope['source_balance']->fresh()->physical_stock);
    }

    /** @return array<int, array{exit:int, output:string, error:string}> */
    private function runConcurrentReversals(array $scope, InventoryDocument $document): array
    {
        $processes = collect([0, 1])->map(function (int $index) use ($scope, $document): Process {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/phase05_reverse_worker.php'),
                (string) $scope['organization']->id,
                (string) $document->id,
                'mysql-reversal:'.$document->id,
                (string) $scope['user']->id,
            ], base_path(), null, null, 90);
            $process->start();

            return $process;
        });

        return $processes->map(function (Process $process): array {
            $process->wait();

            return ['exit' => $process->getExitCode() ?? -1, 'output' => $process->getOutput(), 'error' => $process->getErrorOutput()];
        })->all();
    }

    /** @param array<int, InventoryTransfer> $transfers @return array<int, array{exit:int, output:string, error:string}> */
    private function runConcurrentDispatches(array $scope, array $transfers): array
    {
        $processes = collect($transfers)->map(function (InventoryTransfer $transfer, int $index) use ($scope): Process {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/phase05_dispatch_worker.php'),
                (string) $scope['organization']->id,
                (string) $transfer->id,
                "mysql-dispatch:{$transfer->id}:{$index}",
                (string) $scope['user']->id,
            ], base_path(), null, null, 90);
            $process->start();

            return $process;
        });

        return $processes->map(function (Process $process): array {
            $process->wait();

            return ['exit' => $process->getExitCode() ?? -1, 'output' => $process->getOutput(), 'error' => $process->getErrorOutput()];
        })->all();
    }

    /** @return array<int, array{exit:int, output:string, error:string}> */
    private function runConcurrentReceipts(array $scope, InventoryTransfer $transfer, int $itemId, int $first, int $second): array
    {
        $processes = collect([$first, $second])->map(function (int $quantity, int $index) use ($scope, $transfer, $itemId): Process {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/phase05_receive_worker.php'),
                (string) $scope['organization']->id,
                (string) $transfer->id,
                (string) $itemId,
                (string) $quantity,
                "mysql-receive:{$transfer->id}:{$index}",
                (string) $scope['user']->id,
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
    private function scope(int $sourceStock): array
    {
        $suffix = uniqid();
        $organization = Organization::query()->create([
            'code' => 'F5M-'.$suffix, 'name' => 'Phase 05 MySQL', 'slug' => 'phase05-mysql-'.$suffix,
            'status' => 'active', 'environment' => 'demo', 'is_default' => true,
        ]);
        $sourceBranch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => 'SRC-'.$suffix, 'name' => 'Source', 'is_active' => true, 'is_default' => true,
        ]);
        $destinationBranch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => 'DST-'.$suffix, 'name' => 'Destination', 'is_active' => true, 'is_default' => false,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'branch_id' => $sourceBranch->id]);
        $this->actingAs($user);
        $category = Category::query()->create([
            'organization_id' => $organization->id, 'name' => 'Physical', 'slug' => 'physical-'.$suffix,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Flour', 'sku' => 'F5M-'.$suffix,
            'slug' => 'flour-'.$suffix, 'tax_affectation' => 'Gravado', 'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value, 'price' => 10, 'purchase_price' => 2,
            'average_price' => 2, 'stock' => $sourceStock, 'min_stock' => 0, 'is_active' => true,
        ]);
        $sourceWarehouse = InventoryWarehouse::query()->create([
            'organization_id' => $organization->id, 'branch_id' => $sourceBranch->id, 'code' => 'WS-'.$suffix,
            'name' => 'Source warehouse', 'is_default' => true, 'is_active' => true,
        ]);
        $destinationWarehouse = InventoryWarehouse::query()->create([
            'organization_id' => $organization->id, 'branch_id' => $destinationBranch->id, 'code' => 'WD-'.$suffix,
            'name' => 'Destination warehouse', 'is_default' => true, 'is_active' => true,
        ]);
        foreach ([[$sourceBranch, $sourceWarehouse, $sourceStock], [$destinationBranch, $destinationWarehouse, 0]] as [$branch, $warehouse, $stock]) {
            ProductBranchStock::query()->create([
                'organization_id' => $organization->id, 'product_id' => $product->id, 'branch_id' => $branch->id,
                'stock' => $stock, 'min_stock' => 0, 'is_active' => true,
            ]);
            ProductWarehouseStock::query()->create([
                'organization_id' => $organization->id, 'product_id' => $product->id, 'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id, 'stock' => $stock, 'min_stock' => 0,
                'average_cost' => 2, 'last_cost' => 2, 'is_active' => true,
            ]);
        }
        app(InventoryLedgerBackfillService::class)->run($organization->id);
        InventoryLedgerRollout::query()->create([
            'organization_id' => $organization->id, 'mode' => InventoryLedgerRolloutMode::Active->value, 'activated_at' => now(),
        ]);

        return [
            'organization' => $organization, 'user' => $user, 'product' => $product,
            'source_branch' => $sourceBranch, 'destination_branch' => $destinationBranch,
            'source_warehouse' => $sourceWarehouse, 'destination_warehouse' => $destinationWarehouse,
            'source_balance' => InventoryBalance::query()->where('warehouse_id', $sourceWarehouse->id)->firstOrFail(),
            'destination_balance' => InventoryBalance::query()->where('warehouse_id', $destinationWarehouse->id)->firstOrFail(),
        ];
    }

    private function transferCommand(array $scope, string $key, int $quantity = 10): InventoryTransferCommand
    {
        return new InventoryTransferCommand(
            organizationId: $scope['organization']->id,
            idempotencyKey: $key,
            sourceWarehouseId: $scope['source_warehouse']->id,
            destinationWarehouseId: $scope['destination_warehouse']->id,
            items: [new InventoryTransferItemData($scope['product']->id, $quantity)],
            actorId: $scope['user']->id,
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Services\InventoryDocumentService;
use Modules\Catalog\Services\InventoryLedgerBackfillService;
use Modules\Catalog\Services\InventoryLedgerRolloutService;
use Modules\Catalog\Services\InventoryMovementService;
use Modules\Catalog\Services\InventoryReconciliationService;
use Modules\Catalog\Services\ProductInventoryService;
use Modules\Operations\Services\OperationalReconciliationService;
use Modules\Security\Models\SecurityBranch;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_movement_updates_ledger_balance_and_legacy_mirror_atomically(): void
    {
        [$organization, $branch, $product] = $this->inventoryScope(10);

        $movement = app(InventoryMovementService::class)->recordOutbound($product, $branch->id, 3, [
            'idempotency_key' => 'sale-1',
            'reason_code' => 'sale',
        ]);

        $this->assertSame(7, $movement->stock_after);
        $this->assertDatabaseHas('inventory_balances', [
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'location_key' => 'unallocated:'.$branch->id,
            'physical_stock' => 7,
            'version' => 2,
        ]);
        $this->assertDatabaseHas('product_branch_stocks', ['product_id' => $product->id, 'branch_id' => $branch->id, 'stock' => 7]);
        $this->assertSame(2, InventoryMovement::query()->where('organization_id', $organization->id)->count());
    }

    public function test_idempotent_replay_does_not_duplicate_and_payload_mismatch_is_rejected(): void
    {
        [, $branch, $product] = $this->inventoryScope(0);
        $service = app(InventoryMovementService::class);
        $first = $service->recordInbound($product, $branch->id, 4, ['idempotency_key' => 'receipt-1', 'unit_cost' => 2]);
        $replayed = $service->recordInbound($product, $branch->id, 4, ['idempotency_key' => 'receipt-1', 'unit_cost' => 2]);

        $this->assertSame($first->id, $replayed->id);
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(4, InventoryBalance::query()->value('physical_stock'));

        $this->expectException(ValidationException::class);
        $service->recordInbound($product, $branch->id, 5, ['idempotency_key' => 'receipt-1', 'unit_cost' => 2]);
    }

    public function test_movements_are_immutable_in_eloquent_and_database(): void
    {
        [, $branch, $product] = $this->inventoryScope(0);
        $movement = app(InventoryMovementService::class)->recordInbound($product, $branch->id, 2, ['idempotency_key' => 'immutable-1']);

        try {
            $movement->forceFill(['notes' => 'changed'])->save();
            $this->fail('Eloquent debio bloquear la actualizacion.');
        } catch (LogicException) {
            $this->assertSame(2, $movement->quantity);
        }

        $this->expectException(QueryException::class);
        \DB::table('inventory_movements')->where('id', $movement->id)->update(['notes' => 'changed']);
    }

    public function test_reversal_is_a_compensating_movement_and_never_edits_original(): void
    {
        [, $branch, $product] = $this->inventoryScope(5);
        $service = app(InventoryMovementService::class);
        $outbound = $service->recordOutbound($product, $branch->id, 2, ['idempotency_key' => 'sale-reverse-1']);
        $reversal = $service->reverse($outbound, idempotencyKey: 'sale-reverse-1:reverse');

        $this->assertSame(2, $reversal->quantity);
        $this->assertSame($outbound->id, $reversal->reversal_of_id);
        $this->assertSame(5, $reversal->stock_after);
        $this->assertSame(-2, $outbound->fresh()->quantity);
        $this->assertSame(5, ProductBranchStock::query()->where('product_id', $product->id)->value('stock'));
    }

    public function test_reversing_an_inbound_movement_restores_the_previous_average_cost(): void
    {
        [, $branch, $product] = $this->inventoryScope(0);
        $service = app(InventoryMovementService::class);
        $service->recordInbound($product, $branch->id, 10, [
            'idempotency_key' => 'purchase-cost-base',
            'unit_cost' => 2,
        ]);
        $inbound = $service->recordInbound($product, $branch->id, 10, [
            'idempotency_key' => 'purchase-cost-restore',
            'unit_cost' => 4,
        ]);

        $this->assertSame('3.0000', $inbound->average_cost_after);
        $reversal = $service->reverse($inbound, idempotencyKey: 'purchase-cost-restore:reverse');

        $this->assertSame('2.0000', $reversal->average_cost_after);
        $this->assertSame('2.0000', InventoryBalance::query()->whereKey($reversal->inventory_balance_id)->value('average_cost'));
    }

    public function test_backfill_is_idempotent_and_separates_warehouse_from_unallocated_stock(): void
    {
        [$organization, $branch, $product, $warehouse] = $this->inventoryScope(10, withWarehouse: true, warehouseStock: 6);
        $service = app(InventoryLedgerBackfillService::class);
        $dryRun = $service->run($organization->id, 10, true);

        $this->assertSame(2, $dryRun['baselines']);
        $this->assertSame(0, InventoryBalance::query()->count());

        $service->run($organization->id, 10, false);
        $service->run($organization->id, 10, false);

        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'location_key' => 'warehouse:'.$warehouse->id, 'physical_stock' => 6]);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'location_key' => 'warehouse:'.$warehouse->id, 'min_stock' => 1]);
        $this->assertDatabaseHas('inventory_balances', ['product_id' => $product->id, 'location_key' => 'unallocated:'.$branch->id, 'physical_stock' => 4, 'min_stock' => 0]);
        $this->assertSame(2, InventoryBalance::query()->count());
        $this->assertSame(2, InventoryMovement::query()->count());
    }

    public function test_rollout_requires_reconciliation_and_can_return_to_legacy_reads(): void
    {
        [$organization, $branch, $product] = $this->inventoryScope(8);
        app(InventoryLedgerBackfillService::class)->run($organization->id);
        $rollout = app(InventoryLedgerRolloutService::class);

        try {
            $rollout->setMode($organization->id, InventoryLedgerRolloutMode::Active);
            $this->fail('No debio activar sin conciliacion.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $run = app(InventoryReconciliationService::class)->run($organization->id);
        $this->assertSame('passed', $run->status);
        $rollout->setMode($organization->id, InventoryLedgerRolloutMode::Active);
        InventoryBalance::query()->where('product_id', $product->id)->update(['physical_stock' => 6]);

        $reader = app(ProductInventoryService::class);
        $this->assertSame(6, $reader->availableStock($product, $branch->id));

        $rollout->setMode($organization->id, InventoryLedgerRolloutMode::Shadow);
        $this->assertSame(8, $reader->availableStock($product, $branch->id));
    }

    public function test_reconciliation_detects_exact_warehouse_drift_even_when_aggregates_still_match(): void
    {
        [$organization, , , $warehouse] = $this->inventoryScope(10, withWarehouse: true, warehouseStock: 6);
        app(InventoryLedgerBackfillService::class)->run($organization->id);
        ProductWarehouseStock::query()->where('warehouse_id', $warehouse->id)->update(['stock' => 5]);

        $run = app(InventoryReconciliationService::class)->run($organization->id);

        $this->assertSame('failed', $run->status);
        $this->assertTrue($run->issues->contains('issue_type', 'warehouse_legacy_mismatch'));
    }

    public function test_operational_reconciliation_detects_a_ledger_head_drift(): void
    {
        [$organization, $branch, $product] = $this->inventoryScope(0);
        $movement = app(InventoryMovementService::class)->recordInbound($product, $branch->id, 5, [
            'idempotency_key' => 'ops-ledger-head-drift',
            'unit_cost' => 2,
        ]);
        InventoryBalance::query()->whereKey($movement->inventory_balance_id)->update(['physical_stock' => 4]);

        $run = app(OperationalReconciliationService::class)->run((int) $organization->id, 'test');

        $this->assertSame('failed', $run->status);
        $this->assertTrue($run->issues->contains('issue_code', 'INV_BALANCE_HEAD_MISMATCH'));
    }

    public function test_operational_reconciliation_detects_a_confirmed_document_item_without_movement(): void
    {
        [$organization, $branch, $product, $warehouse] = $this->inventoryScope(0, withWarehouse: true, warehouseStock: 0);
        $document = InventoryDocument::query()->create([
            'organization_id' => $organization->id,
            'code' => 'OPS-DOC-'.uniqid(),
            'idempotency_key' => 'ops-doc-missing-movement',
            'payload_hash' => hash('sha256', 'ops-doc-missing-movement'),
            'document_type' => 'inbound',
            'status' => 'draft',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'issued_at' => now(),
        ]);
        $document->items()->create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 2,
            'line_total' => 10,
        ]);
        $document->forceFill(['status' => 'confirmed', 'confirmed_at' => now()])->save();

        $run = app(OperationalReconciliationService::class)->run((int) $organization->id, 'test');

        $this->assertSame('failed', $run->status);
        $this->assertTrue($run->issues->contains('issue_code', 'DOC_ITEM_MOVEMENT_MISSING'));
    }

    public function test_confirmed_document_rejects_new_items(): void
    {
        [$organization, $branch, $product, $warehouse] = $this->inventoryScope(0, withWarehouse: true, warehouseStock: 0);
        $document = InventoryDocument::query()->create([
            'organization_id' => $organization->id,
            'code' => 'OPS-LOCK-'.uniqid(),
            'document_type' => 'inbound',
            'status' => 'confirmed',
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'issued_at' => now(),
            'confirmed_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $document->items()->create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_backfill_preserves_inactive_warehouse_stock_as_unavailable(): void
    {
        [$organization, $branch, $product, $warehouse] = $this->inventoryScope(10, withWarehouse: true, warehouseStock: 6);
        ProductWarehouseStock::query()->where('warehouse_id', $warehouse->id)->update(['is_active' => false]);

        app(InventoryLedgerBackfillService::class)->run($organization->id);

        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'location_key' => 'warehouse:'.$warehouse->id,
            'physical_stock' => 6,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'product_id' => $product->id,
            'location_key' => 'unallocated:'.$branch->id,
            'physical_stock' => 10,
            'is_active' => true,
        ]);
    }

    public function test_opening_stock_and_adjustment_are_formal_idempotent_documents(): void
    {
        [$organization, $branch, $product, $warehouse] = $this->inventoryScope(0, withWarehouse: true, warehouseStock: 0);
        $this->actingAs(User::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
        ]));
        app(InventoryLedgerBackfillService::class)->run($organization->id);
        $documents = app(InventoryDocumentService::class);
        $opening = $documents->createDraft($this->documentPayload($branch->id, $warehouse->id, $product->id, 'opening_stock', 5, null, 2));
        $documents->confirm($opening->id);
        $documents->confirm($opening->id);

        $adjustment = $documents->createDraft($this->documentPayload($branch->id, $warehouse->id, $product->id, 'stock_adjustment', 0, 0));
        $documents->confirm($adjustment->id);

        $this->assertSame(3, InventoryMovement::query()->where('warehouse_id', $warehouse->id)->count());
        $this->assertSame(0, InventoryBalance::query()->where('warehouse_id', $warehouse->id)->value('physical_stock'));
        $this->assertSame(0, ProductWarehouseStock::query()->where('warehouse_id', $warehouse->id)->value('stock'));
        $this->assertSame(0, $adjustment->items()->value('quantity'));

        $this->expectException(LogicException::class);
        $opening->fresh()->forceFill(['notes' => 'changed'])->save();
    }

    public function test_ledger_rejects_cross_tenant_locations(): void
    {
        [, , $product] = $this->inventoryScope(2);
        [, $foreignBranch] = $this->inventoryScope(2, 'FOREIGN');

        $this->expectException(ValidationException::class);
        app(InventoryMovementService::class)->recordInbound($product, $foreignBranch->id, 1, ['idempotency_key' => 'cross-tenant']);
    }

    public function test_document_service_rejects_an_organization_outside_the_active_context(): void
    {
        [$organization, $branch] = $this->inventoryScope(0);
        [, $foreignBranch, $foreignProduct, $foreignWarehouse] = $this->inventoryScope(0, 'FOREIGN-DOC', true, 0);
        $this->actingAs(User::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
        ]));

        $this->expectException(ValidationException::class);
        app(InventoryDocumentService::class)->createDraft(
            $this->documentPayload($foreignBranch->id, $foreignWarehouse->id, $foreignProduct->id, 'inbound', 1, null, 2)
        );
    }

    public function test_non_inventory_products_cannot_generate_physical_movements(): void
    {
        [, $branch, $product] = $this->inventoryScope(0);
        $product->forceFill(['product_type' => ProductType::Service->value])->save();

        $this->expectException(ValidationException::class);
        app(InventoryMovementService::class)->recordInbound($product->fresh(), $branch->id, 1, ['idempotency_key' => 'service-stock']);
    }

    /** @return array{0:Organization,1:SecurityBranch,2:Product,3?:InventoryWarehouse} */
    private function inventoryScope(int $branchStock, string $code = 'MAIN', bool $withWarehouse = false, int $warehouseStock = 0): array
    {
        $organization = Organization::query()->create([
            'code' => $code.'-'.uniqid(),
            'name' => 'Organization '.$code,
            'slug' => strtolower($code).'-'.uniqid(),
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => Organization::query()->doesntExist(),
        ]);
        $branch = SecurityBranch::query()->create([
            'organization_id' => $organization->id,
            'code' => $code.'-'.uniqid(),
            'name' => 'Branch '.$code,
            'is_active' => true,
            'is_default' => true,
        ]);
        $category = Category::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Category '.$code,
            'slug' => 'category-'.strtolower($code).'-'.uniqid(),
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id,
            'category_id' => $category->id,
            'name' => 'Product '.$code,
            'sku' => 'SKU-'.uniqid(),
            'slug' => 'product-'.strtolower($code).'-'.uniqid(),
            'tax_affectation' => 'Gravado',
            'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 10,
            'purchase_price' => 2,
            'average_price' => 2,
            'stock' => $branchStock,
            'min_stock' => 1,
            'is_active' => true,
        ]);
        ProductBranchStock::query()->create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'stock' => $branchStock,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        if (! $withWarehouse) {
            return [$organization, $branch, $product];
        }

        $warehouse = InventoryWarehouse::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'code' => 'WH-'.uniqid(),
            'name' => 'Warehouse '.$code,
            'is_default' => true,
            'is_active' => true,
        ]);
        ProductWarehouseStock::query()->create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'stock' => $warehouseStock,
            'min_stock' => 1,
            'average_cost' => 2,
            'last_cost' => 2,
            'is_active' => true,
        ]);

        return [$organization, $branch, $product, $warehouse];
    }

    /** @return array<string, mixed> */
    private function documentPayload(int $branchId, int $warehouseId, int $productId, string $type, int $quantity, ?int $target = null, ?float $unitCost = null): array
    {
        return [
            'organization_id' => Product::query()->findOrFail($productId)->organization_id,
            'document_type' => $type,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'reason' => $type,
            'items' => [[
                'product_id' => $productId,
                'quantity' => $quantity,
                'target_quantity' => $target,
                'unit_cost' => $unitCost,
            ]],
        ];
    }
}

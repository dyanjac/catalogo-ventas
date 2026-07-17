<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Data\InventoryReservationCommand;
use Modules\Catalog\Data\InventoryReservationItemData;
use Modules\Catalog\Data\InventoryTransferCommand;
use Modules\Catalog\Data\InventoryTransferItemData;
use Modules\Catalog\Data\InventoryTransferReceiptCommand;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryTransferEvent;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\InventoryDocumentStatus;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Enums\InventoryReservationStatus;
use Modules\Catalog\Enums\InventoryTransferStatus;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Services\InventoryDocumentService;
use Modules\Catalog\Services\InventoryLedgerBackfillService;
use Modules\Catalog\Services\InventoryLedgerRolloutService;
use Modules\Catalog\Services\InventoryReconciliationService;
use Modules\Catalog\Services\InventoryReservationService;
use Modules\Catalog\Services\InventoryTransferService;
use Modules\Security\Models\SecurityBranch;
use Tests\TestCase;

class InventoryWarehouseOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_dispatch_consumes_physical_and_reserved_stock_atomically(): void
    {
        $scope = $this->scope();
        $reservation = app(InventoryReservationService::class)->reserve(new InventoryReservationCommand(
            organizationId: $scope['organization']->id,
            idempotencyKey: 'dispatch-reservation',
            items: [new InventoryReservationItemData($scope['source_balance']->id, 4)],
        ));
        $documents = app(InventoryDocumentService::class);
        $draft = $documents->createDraft($this->documentPayload($scope, 'dispatch', 4, [
            'reservation_id' => $reservation->id,
            'idempotency_key' => 'document-dispatch-1',
        ]));

        $confirmed = $documents->confirm($draft->id, $scope['user']->id);
        $replay = $documents->confirm($draft->id, $scope['user']->id);

        $this->assertSame($confirmed->id, $replay->id);
        $this->assertSame(InventoryDocumentStatus::Confirmed, $confirmed->status);
        $this->assertSame(InventoryReservationStatus::Consumed, $reservation->fresh()->status);
        $this->assertSame(6, $scope['source_balance']->fresh()->physical_stock);
        $this->assertSame(0, $scope['source_balance']->fresh()->reserved_stock);
        $this->assertSame(1, InventoryMovement::query()->where('reference_type', InventoryDocument::class)->where('reference_id', $draft->id)->count());
    }

    public function test_customer_return_and_document_compensation_are_idempotent(): void
    {
        $scope = $this->scope();
        $documents = app(InventoryDocumentService::class);
        $draft = $documents->createDraft($this->documentPayload($scope, 'customer_return', 2, [
            'idempotency_key' => 'customer-return-1',
            'items' => [[
                'product_id' => $scope['product']->id,
                'quantity' => 2,
                'unit_cost' => 2,
            ]],
        ]));
        $documents->confirm($draft->id, $scope['user']->id);
        $this->assertSame(12, $scope['source_balance']->fresh()->physical_stock);

        $reversal = $documents->reverse($draft->id, 'customer-return-1:reverse', $scope['user']->id);
        $replayed = $documents->reverse($draft->id, 'customer-return-1:reverse', $scope['user']->id);

        $this->assertSame($reversal->id, $replayed->id);
        $this->assertSame($draft->id, $reversal->reversal_of_id);
        $this->assertSame(10, $scope['source_balance']->fresh()->physical_stock);
        $this->assertSame(InventoryDocumentStatus::Confirmed, $draft->fresh()->status);
        $this->assertSame(1, InventoryMovement::query()->whereNotNull('reversal_of_id')->count());

        $this->expectException(ValidationException::class);
        $documents->reverse($draft->id, 'customer-return-1:reverse', $scope['user']->id, 'different_reason');
    }

    public function test_advanced_document_confirmation_requires_active_rollout(): void
    {
        $scope = $this->scope();
        $documents = app(InventoryDocumentService::class);
        $draft = $documents->createDraft($this->documentPayload($scope, 'receipt', 2, [
            'idempotency_key' => 'inactive-rollout-document',
            'items' => [['product_id' => $scope['product']->id, 'quantity' => 2, 'unit_cost' => 2]],
        ]));
        InventoryLedgerRollout::query()->where('organization_id', $scope['organization']->id)->update([
            'mode' => InventoryLedgerRolloutMode::Shadow->value,
        ]);

        try {
            $documents->confirm($draft->id, $scope['user']->id);
            $this->fail('La confirmacion debio exigir rollout active.');
        } catch (ValidationException) {
            $this->assertSame(10, $scope['source_balance']->fresh()->physical_stock);
            $this->assertSame(InventoryDocumentStatus::Draft, $draft->fresh()->status);
        }
    }

    public function test_transfer_dispatch_and_partial_receipts_preserve_physical_plus_transit(): void
    {
        $scope = $this->scope();
        $service = app(InventoryTransferService::class);
        $transfer = $service->create($this->transferCommand($scope, 10, 'transfer-1'));

        $this->assertSame(InventoryTransferStatus::Draft, $transfer->status);
        $this->assertSame(10, $scope['source_balance']->fresh()->physical_stock);
        $service->dispatch($scope['organization']->id, $transfer->id, 'transfer-1:dispatch', $scope['user']->id);
        $transfer->refresh();

        $this->assertSame(InventoryTransferStatus::InTransit, $transfer->status);
        $this->assertSame(0, $scope['source_balance']->fresh()->physical_stock);
        $this->assertSame(0, $scope['destination_balance']->fresh()->physical_stock);
        $this->assertSame(10, $scope['destination_balance']->fresh()->in_transit_stock);
        $itemId = $transfer->items()->value('id');

        $firstReceipt = new InventoryTransferReceiptCommand(
            organizationId: $scope['organization']->id,
            transferId: $transfer->id,
            idempotencyKey: 'transfer-1:receive-4',
            quantitiesByItemId: [$itemId => 4],
            actorId: $scope['user']->id,
        );
        $partial = $service->receive($firstReceipt);
        $replay = $service->receive($firstReceipt);
        $this->assertSame($partial->id, $replay->id);
        $this->assertSame(InventoryTransferStatus::PartiallyReceived, $partial->status);
        $this->assertSame(4, $scope['destination_balance']->fresh()->physical_stock);
        $this->assertSame(6, $scope['destination_balance']->fresh()->in_transit_stock);

        $completed = $service->receive(new InventoryTransferReceiptCommand(
            organizationId: $scope['organization']->id,
            transferId: $transfer->id,
            idempotencyKey: 'transfer-1:receive-6',
            quantitiesByItemId: [$itemId => 6],
            actorId: $scope['user']->id,
        ));
        $this->assertSame(InventoryTransferStatus::Received, $completed->status);
        $this->assertSame(10, $scope['destination_balance']->fresh()->physical_stock);
        $this->assertSame(0, $scope['destination_balance']->fresh()->in_transit_stock);
        $this->assertSame(10, $scope['source_balance']->fresh()->physical_stock + $scope['destination_balance']->fresh()->physical_stock + $scope['destination_balance']->fresh()->in_transit_stock);
        $this->assertSame(3, InventoryTransferEvent::query()->where('transfer_id', $transfer->id)->whereIn('event_type', ['dispatched', 'received'])->count());
    }

    public function test_transfer_rejects_over_receipt_without_partial_effects(): void
    {
        $scope = $this->scope();
        $service = app(InventoryTransferService::class);
        $transfer = $service->create($this->transferCommand($scope, 5, 'transfer-over'));
        $service->dispatch($scope['organization']->id, $transfer->id, 'transfer-over:dispatch', $scope['user']->id);
        $itemId = $transfer->items()->value('id');

        try {
            $service->receive(new InventoryTransferReceiptCommand(
                organizationId: $scope['organization']->id,
                transferId: $transfer->id,
                idempotencyKey: 'transfer-over:receive',
                quantitiesByItemId: [$itemId => 6],
            ));
            $this->fail('La sobre-recepcion debio rechazarse.');
        } catch (ValidationException) {
            $this->assertSame(5, $scope['destination_balance']->fresh()->in_transit_stock);
            $this->assertSame(0, $scope['destination_balance']->fresh()->physical_stock);
            $this->assertSame(0, (int) $transfer->items()->value('received_quantity'));
        }
    }

    public function test_draft_transfer_can_cancel_without_moving_stock(): void
    {
        $scope = $this->scope();
        $service = app(InventoryTransferService::class);
        $transfer = $service->create($this->transferCommand($scope, 3, 'transfer-cancel'));
        $cancelled = $service->cancelDraft($scope['organization']->id, $transfer->id, 'transfer-cancel:event', $scope['user']->id);
        $replay = $service->cancelDraft($scope['organization']->id, $transfer->id, 'transfer-cancel:event', $scope['user']->id);

        $this->assertSame($cancelled->id, $replay->id);
        $this->assertSame(InventoryTransferStatus::Cancelled, $cancelled->status);
        $this->assertSame(10, $scope['source_balance']->fresh()->physical_stock);
        $this->assertSame(0, $scope['destination_balance']->fresh()->in_transit_stock);
    }

    public function test_rollout_downgrade_is_blocked_while_transfer_is_in_transit(): void
    {
        $scope = $this->scope();
        $service = app(InventoryTransferService::class);
        $transfer = $service->create($this->transferCommand($scope, 2, 'transfer-rollout'));
        $service->dispatch($scope['organization']->id, $transfer->id, 'transfer-rollout:dispatch', $scope['user']->id);

        $this->expectException(ValidationException::class);
        app(InventoryLedgerRolloutService::class)->setMode($scope['organization']->id, InventoryLedgerRolloutMode::Shadow);
    }

    public function test_document_creation_is_atomic_idempotent_and_payload_conflicts_fail(): void
    {
        $scope = $this->scope();
        $documents = app(InventoryDocumentService::class);
        $payload = $this->documentPayload($scope, 'inbound', 2, [
            'idempotency_key' => 'document-idempotent',
            'items' => [['product_id' => $scope['product']->id, 'quantity' => 2, 'unit_cost' => 2]],
        ]);
        $first = $documents->createDraft($payload);
        $replay = $documents->createDraft($payload);
        $this->assertSame($first->id, $replay->id);
        $this->assertDatabaseCount('inventory_documents', 1);

        try {
            $documents->createDraft(array_replace($payload, [
                'items' => [['product_id' => $scope['product']->id, 'quantity' => 3, 'unit_cost' => 2]],
            ]));
            $this->fail('El conflicto de payload debio fallar.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_documents', 1);
            $this->assertDatabaseCount('inventory_document_items', 1);
        }
    }

    public function test_transfer_create_replays_and_rejects_same_key_with_different_payload(): void
    {
        $scope = $this->scope();
        $service = app(InventoryTransferService::class);
        $first = $service->create($this->transferCommand($scope, 2, 'transfer-idempotent'));
        $replay = $service->create($this->transferCommand($scope, 2, 'transfer-idempotent'));
        $this->assertSame($first->id, $replay->id);

        $this->expectException(ValidationException::class);
        $service->create($this->transferCommand($scope, 3, 'transfer-idempotent'));
    }

    public function test_reconciliation_detects_transit_drift(): void
    {
        $scope = $this->scope();
        $service = app(InventoryTransferService::class);
        $transfer = $service->create($this->transferCommand($scope, 3, 'transfer-drift'));
        $service->dispatch($scope['organization']->id, $transfer->id, 'transfer-drift:dispatch', $scope['user']->id);
        InventoryBalance::query()->whereKey($scope['destination_balance']->id)->update(['in_transit_stock' => 2]);

        $run = app(InventoryReconciliationService::class)->run($scope['organization']->id);
        $this->assertSame('failed', $run->status);
        $this->assertTrue($run->issues->contains('issue_type', 'transit_balance_mismatch'));
    }

    public function test_confirmed_documents_and_transfer_events_are_database_immutable(): void
    {
        $scope = $this->scope();
        $documents = app(InventoryDocumentService::class);
        $document = $documents->createDraft($this->documentPayload($scope, 'inbound', 1, [
            'idempotency_key' => 'immutable-document',
            'items' => [['product_id' => $scope['product']->id, 'quantity' => 1, 'unit_cost' => 2]],
        ]));
        $documents->confirm($document->id, $scope['user']->id);

        try {
            DB::table('inventory_documents')->where('id', $document->id)->update(['notes' => 'changed']);
            $this->fail('El trigger debio impedir editar el documento confirmado.');
        } catch (QueryException) {
            $this->assertNull($document->fresh()->notes);
        }

        $service = app(InventoryTransferService::class);
        $transfer = $service->create($this->transferCommand($scope, 1, 'immutable-transfer'));
        $this->expectException(QueryException::class);
        DB::table('inventory_transfer_events')->where('transfer_id', $transfer->id)->delete();
    }

    public function test_database_triggers_reject_cross_tenant_document_and_transfer_links(): void
    {
        $scope = $this->scope();
        $other = $this->scope();
        $document = app(InventoryDocumentService::class)->createDraft($this->documentPayload($other, 'inbound', 1, [
            'idempotency_key' => 'other-tenant-document',
            'items' => [['product_id' => $other['product']->id, 'quantity' => 1, 'unit_cost' => 2]],
        ]));

        try {
            DB::table('inventory_document_items')->insert([
                'organization_id' => $scope['organization']->id,
                'document_id' => $document->id,
                'product_id' => $scope['product']->id,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('El trigger documental debio rechazar el cruce tenant.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('inventory_document_items', [
                'organization_id' => $scope['organization']->id,
                'document_id' => $document->id,
            ]);
        }

        $transfer = app(InventoryTransferService::class)->create($this->transferCommand($other, 1, 'other-tenant-transfer'));
        $this->expectException(QueryException::class);
        DB::table('inventory_transfer_items')->insert([
            'organization_id' => $scope['organization']->id,
            'transfer_id' => $transfer->id,
            'product_id' => $scope['product']->id,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function scope(): array
    {
        $organization = Organization::query()->create([
            'code' => 'F5-'.uniqid(), 'name' => 'Phase 05', 'slug' => 'phase05-'.uniqid(),
            'status' => 'active', 'environment' => 'demo', 'is_default' => true,
        ]);
        $sourceBranch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => 'SRC-'.uniqid(), 'name' => 'Source', 'is_active' => true, 'is_default' => true,
        ]);
        $destinationBranch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => 'DST-'.uniqid(), 'name' => 'Destination', 'is_active' => true, 'is_default' => false,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'branch_id' => $sourceBranch->id]);
        $this->actingAs($user);
        $category = Category::query()->create([
            'organization_id' => $organization->id, 'name' => 'Physical', 'slug' => 'physical-'.uniqid(),
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Flour', 'sku' => 'F5-'.uniqid(),
            'slug' => 'flour-'.uniqid(), 'tax_affectation' => 'Gravado', 'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value, 'price' => 10, 'purchase_price' => 2,
            'average_price' => 2, 'stock' => 10, 'min_stock' => 0, 'is_active' => true,
        ]);
        $sourceWarehouse = InventoryWarehouse::query()->create([
            'organization_id' => $organization->id, 'branch_id' => $sourceBranch->id, 'code' => 'WS-'.uniqid(),
            'name' => 'Source warehouse', 'is_default' => true, 'is_active' => true,
        ]);
        $destinationWarehouse = InventoryWarehouse::query()->create([
            'organization_id' => $organization->id, 'branch_id' => $destinationBranch->id, 'code' => 'WD-'.uniqid(),
            'name' => 'Destination warehouse', 'is_default' => true, 'is_active' => true,
        ]);
        foreach ([[$sourceBranch, $sourceWarehouse, 10], [$destinationBranch, $destinationWarehouse, 0]] as [$branch, $warehouse, $stock]) {
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

    private function transferCommand(array $scope, int $quantity, string $key): InventoryTransferCommand
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

    private function documentPayload(array $scope, string $type, int $quantity, array $overrides = []): array
    {
        return array_replace([
            'organization_id' => $scope['organization']->id,
            'document_type' => $type,
            'branch_id' => $scope['source_branch']->id,
            'warehouse_id' => $scope['source_warehouse']->id,
            'created_by' => $scope['user']->id,
            'items' => [['product_id' => $scope['product']->id, 'quantity' => $quantity]],
        ], $overrides);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Data\InventoryReservationCommand;
use Modules\Catalog\Data\InventoryReservationItemData;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryReservationEvent;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Enums\InventoryReservationStatus;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Services\InventoryLedgerBackfillService;
use Modules\Catalog\Services\InventoryLedgerRolloutService;
use Modules\Catalog\Services\InventoryMovementService;
use Modules\Catalog\Services\InventoryReconciliationService;
use Modules\Catalog\Services\InventoryReservationService;
use Modules\Catalog\Services\ProductInventoryService;
use Modules\Security\Models\SecurityBranch;
use Tests\TestCase;

class InventoryReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserve_is_atomic_idempotent_and_changes_available_not_physical_stock(): void
    {
        [$organization, $branch, $product] = $this->activeLedgerScope(10);
        $balance = InventoryBalance::query()->firstOrFail();
        $service = app(InventoryReservationService::class);
        $command = $this->reserveCommand($organization->id, 'cart:100', [$balance->id => 4]);

        $first = $service->reserve($command);
        $replay = $service->reserve($command);

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(10, $balance->fresh()->physical_stock);
        $this->assertSame(4, $balance->fresh()->reserved_stock);
        $this->assertSame(6, app(ProductInventoryService::class)->availableStock($product, $branch->id));
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertDatabaseCount('inventory_reservation_events', 1);

        $this->expectException(ValidationException::class);
        $service->reserve($this->reserveCommand($organization->id, 'cart:100', [$balance->id => 5]));
    }

    public function test_ledger_available_stock_without_branch_context_still_subtracts_reservations(): void
    {
        [$organization, $branch, $product] = $this->activeLedgerScope(10);
        $balance = InventoryBalance::query()->firstOrFail();
        app(InventoryReservationService::class)->reserve($this->reserveCommand($organization->id, 'cart:no-branch', [$balance->id => 4]));
        $branch->forceFill(['is_default' => false])->save();

        $this->assertSame(6, app(ProductInventoryService::class)->availableStock($product));
    }

    public function test_multi_balance_reservation_is_all_or_nothing_and_rejects_overbooking(): void
    {
        [$organization] = $this->activeLedgerScope(10, true, 6);
        $balances = InventoryBalance::query()->orderBy('id')->get();
        $before = $balances->pluck('reserved_stock', 'id')->all();

        try {
            app(InventoryReservationService::class)->reserve($this->reserveCommand($organization->id, 'cart:atomic', [
                $balances[0]->id => $balances[0]->physical_stock,
                $balances[1]->id => $balances[1]->physical_stock + 1,
            ]));
            $this->fail('La reserva debio fallar por stock insuficiente.');
        } catch (ValidationException) {
            $this->assertSame($before, InventoryBalance::query()->orderBy('id')->pluck('reserved_stock', 'id')->all());
            $this->assertDatabaseCount('inventory_reservations', 0);
        }

        $exact = [];
        foreach ($balances as $balance) {
            $exact[$balance->id] = (int) $balance->physical_stock;
        }
        app(InventoryReservationService::class)->reserve($this->reserveCommand($organization->id, 'cart:exact', $exact));
        $this->assertSame(0, (int) InventoryBalance::query()->sum(DB::raw('physical_stock - reserved_stock')));
    }

    public function test_release_is_idempotent_and_terminal_transitions_do_not_move_physical_stock(): void
    {
        [$organization] = $this->activeLedgerScope(8);
        $balance = InventoryBalance::query()->firstOrFail();
        $service = app(InventoryReservationService::class);
        $reservation = $service->reserve($this->reserveCommand($organization->id, 'cart:release', [$balance->id => 3]));

        $released = $service->release($organization->id, $reservation->id, 'cart:release:cancel', meta: ['reason' => 'cancelled']);
        $replay = $service->release($organization->id, $reservation->id, 'cart:release:cancel', meta: ['reason' => 'cancelled']);

        $this->assertSame($released->id, $replay->id);
        $this->assertSame(InventoryReservationStatus::Released, $released->status);
        $this->assertSame(8, $balance->fresh()->physical_stock);
        $this->assertSame(0, $balance->fresh()->reserved_stock);
        $this->assertDatabaseCount('inventory_reservation_events', 2);

        $this->expectException(ValidationException::class);
        $service->release($organization->id, $reservation->id, 'cart:release:second');
    }

    public function test_release_can_free_a_balance_that_was_deactivated_after_reserving(): void
    {
        [$organization] = $this->activeLedgerScope(5);
        $balance = InventoryBalance::query()->firstOrFail();
        $service = app(InventoryReservationService::class);
        $reservation = $service->reserve($this->reserveCommand($organization->id, 'cart:inactive-balance', [$balance->id => 2]));
        $balance->forceFill(['is_active' => false])->save();

        $service->release($organization->id, $reservation->id, 'cart:inactive-balance:release');

        $this->assertSame(0, $balance->fresh()->reserved_stock);
        $this->assertSame(InventoryReservationStatus::Released, $reservation->fresh()->status);
    }

    public function test_expiration_command_releases_only_due_reservations_and_is_repeatable(): void
    {
        [$organization] = $this->activeLedgerScope(5);
        $balance = InventoryBalance::query()->firstOrFail();
        $reservation = app(InventoryReservationService::class)->reserve(new InventoryReservationCommand(
            organizationId: $organization->id,
            idempotencyKey: 'cart:expires',
            items: [new InventoryReservationItemData($balance->id, 2)],
            expiresAt: now()->addMinute(),
        ));

        $this->artisan('inventory:reservations-expire', ['--organization' => $organization->id])->assertSuccessful();
        $this->assertSame(InventoryReservationStatus::Active, $reservation->fresh()->status);

        $this->travel(1)->minutes();
        $this->artisan('inventory:reservations-expire', ['--organization' => $organization->id])->assertSuccessful();
        $this->artisan('inventory:reservations-expire', ['--organization' => $organization->id])->assertSuccessful();

        $this->assertSame(InventoryReservationStatus::Expired, $reservation->fresh()->status);
        $this->assertSame(0, $balance->fresh()->reserved_stock);
        $this->assertDatabaseCount('inventory_reservation_events', 2);
    }

    public function test_future_reservation_cannot_expire_and_original_reserve_replay_survives_rollout_change(): void
    {
        [$organization] = $this->activeLedgerScope(5);
        $balance = InventoryBalance::query()->firstOrFail();
        $service = app(InventoryReservationService::class);
        $command = new InventoryReservationCommand(
            organizationId: $organization->id,
            idempotencyKey: 'cart:future',
            items: [new InventoryReservationItemData($balance->id, 2)],
            expiresAt: now()->addMinute(),
        );
        $reservation = $service->reserve($command);

        try {
            $service->expire($organization->id, $reservation->id, 'cart:future:early-expire');
            $this->fail('No debio expirar una reserva futura.');
        } catch (ValidationException) {
            $this->assertSame(2, $balance->fresh()->reserved_stock);
        }

        $service->release($organization->id, $reservation->id, 'cart:future:release');
        InventoryLedgerRollout::query()->where('organization_id', $organization->id)->update(['mode' => 'shadow']);
        $this->travel(2)->minutes();

        $this->assertSame($reservation->id, $service->reserve($command)->id);
        $this->assertSame(0, $balance->fresh()->reserved_stock);
    }

    public function test_physical_movements_cannot_consume_reserved_stock(): void
    {
        [$organization, $branch, $product] = $this->activeLedgerScope(10);
        $balance = InventoryBalance::query()->firstOrFail();
        app(InventoryReservationService::class)->reserve($this->reserveCommand($organization->id, 'cart:protected', [$balance->id => 7]));

        try {
            app(InventoryMovementService::class)->recordOutbound($product, $branch->id, 4, ['idempotency_key' => 'sale:invades']);
            $this->fail('El ledger debio impedir consumir stock reservado.');
        } catch (ValidationException) {
            $this->assertSame(10, $balance->fresh()->physical_stock);
            $this->assertSame(10, ProductBranchStock::query()->where('product_id', $product->id)->value('stock'));
        }
    }

    public function test_reservations_are_tenant_safe_and_require_active_rollout(): void
    {
        [$organization] = $this->activeLedgerScope(4);
        $ownBalance = InventoryBalance::query()->firstOrFail();
        [$foreignOrganization] = $this->activeLedgerScope(4, false, 0, 'FOREIGN');
        $foreignBalance = InventoryBalance::query()->where('organization_id', $foreignOrganization->id)->firstOrFail();

        try {
            app(InventoryReservationService::class)->reserve($this->reserveCommand($organization->id, 'cross-tenant', [$foreignBalance->id => 1]));
            $this->fail('No debio reservar un saldo de otra organizacion.');
        } catch (ValidationException) {
            $this->assertSame(0, $ownBalance->fresh()->reserved_stock);
            $this->assertSame(0, $foreignBalance->fresh()->reserved_stock);
        }

        InventoryLedgerRollout::query()->where('organization_id', $organization->id)->update(['mode' => 'shadow']);
        $this->expectException(ValidationException::class);
        app(InventoryReservationService::class)->reserve($this->reserveCommand($organization->id, 'shadow-denied', [$ownBalance->id => 1]));
    }

    public function test_rollout_cannot_downgrade_until_operational_release_finishes(): void
    {
        [$organization] = $this->activeLedgerScope(3);
        $balance = InventoryBalance::query()->firstOrFail();
        app(InventoryReservationService::class)->reserve($this->reserveCommand($organization->id, 'cart:rollback', [$balance->id => 1]));
        $rollout = app(InventoryLedgerRolloutService::class);

        try {
            $rollout->setMode($organization->id, InventoryLedgerRolloutMode::Shadow);
            $this->fail('El rollback debio bloquearse con reservas activas.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->artisan('inventory:reservations-release', ['organization' => $organization->id, '--all-active' => true])->assertSuccessful();
        $rollout->setMode($organization->id, InventoryLedgerRolloutMode::Shadow);
        $this->assertSame(InventoryLedgerRolloutMode::Shadow, InventoryLedgerRollout::query()->where('organization_id', $organization->id)->firstOrFail()->mode);
    }

    public function test_reconciliation_detects_reserved_drift_and_expired_active_reservations(): void
    {
        [$organization] = $this->activeLedgerScope(5);
        $balance = InventoryBalance::query()->firstOrFail();
        app(InventoryReservationService::class)->reserve(new InventoryReservationCommand(
            organizationId: $organization->id,
            idempotencyKey: 'cart:reconciliation-expired',
            items: [new InventoryReservationItemData($balance->id, 2)],
            expiresAt: now()->addMinute(),
        ));
        $this->travel(2)->minutes();
        InventoryBalance::query()->whereKey($balance->id)->update(['reserved_stock' => 1]);

        $run = app(InventoryReconciliationService::class)->run($organization->id);

        $this->assertSame('failed', $run->status);
        $this->assertTrue($run->issues->contains('issue_type', 'reservation_balance_mismatch'));
        $this->assertTrue($run->issues->contains('issue_type', 'active_reservation_expired'));
    }

    public function test_reservation_items_and_events_are_database_immutable(): void
    {
        [$organization] = $this->activeLedgerScope(2);
        $balance = InventoryBalance::query()->firstOrFail();
        app(InventoryReservationService::class)->reserve($this->reserveCommand($organization->id, 'cart:immutable', [$balance->id => 1]));

        try {
            DB::table('inventory_reservation_items')->update(['quantity' => 2]);
            $this->fail('El trigger debio impedir editar items.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('inventory_reservation_items')->value('quantity'));
        }

        $this->expectException(QueryException::class);
        InventoryReservationEvent::query()->toBase()->delete();
    }

    /** @return array{0:Organization,1:SecurityBranch,2:Product,3?:InventoryWarehouse} */
    private function activeLedgerScope(int $branchStock, bool $withWarehouse = false, int $warehouseStock = 0, string $code = 'MAIN'): array
    {
        $scope = $this->inventoryScope($branchStock, $code, $withWarehouse, $warehouseStock);
        $organization = $scope[0];
        app(InventoryLedgerBackfillService::class)->run($organization->id);
        InventoryLedgerRollout::query()->create([
            'organization_id' => $organization->id,
            'mode' => InventoryLedgerRolloutMode::Active->value,
            'activated_at' => now(),
        ]);

        return $scope;
    }

    /** @param array<int, int> $items */
    private function reserveCommand(int $organizationId, string $key, array $items): InventoryReservationCommand
    {
        return new InventoryReservationCommand(
            organizationId: $organizationId,
            idempotencyKey: $key,
            items: array_map(
                fn (int $quantity, int $balanceId) => new InventoryReservationItemData($balanceId, $quantity),
                $items,
                array_keys($items),
            ),
            sourceType: 'cart',
            sourceCode: $key,
        );
    }

    /** @return array{0:Organization,1:SecurityBranch,2:Product,3?:InventoryWarehouse} */
    private function inventoryScope(int $branchStock, string $code, bool $withWarehouse, int $warehouseStock): array
    {
        $organization = Organization::query()->create([
            'code' => $code.'-'.uniqid(), 'name' => 'Organization '.$code, 'slug' => strtolower($code).'-'.uniqid(),
            'status' => 'active', 'environment' => 'demo', 'is_default' => Organization::query()->doesntExist(),
        ]);
        $branch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => $code.'-'.uniqid(), 'name' => 'Branch '.$code,
            'is_active' => true, 'is_default' => true,
        ]);
        $category = Category::query()->create([
            'organization_id' => $organization->id, 'name' => 'Category '.$code,
            'slug' => 'category-'.strtolower($code).'-'.uniqid(), 'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Product '.$code,
            'sku' => 'SKU-'.uniqid(), 'slug' => 'product-'.strtolower($code).'-'.uniqid(), 'tax_affectation' => 'Gravado',
            'product_type' => ProductType::PhysicalGood->value, 'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 10, 'purchase_price' => 2, 'average_price' => 2, 'stock' => $branchStock, 'min_stock' => 1, 'is_active' => true,
        ]);
        ProductBranchStock::query()->create([
            'organization_id' => $organization->id, 'product_id' => $product->id, 'branch_id' => $branch->id,
            'stock' => $branchStock, 'min_stock' => 1, 'is_active' => true,
        ]);

        if (! $withWarehouse) {
            return [$organization, $branch, $product];
        }

        $warehouse = InventoryWarehouse::query()->create([
            'organization_id' => $organization->id, 'branch_id' => $branch->id, 'code' => 'WH-'.uniqid(),
            'name' => 'Warehouse '.$code, 'is_default' => true, 'is_active' => true,
        ]);
        ProductWarehouseStock::query()->create([
            'organization_id' => $organization->id, 'product_id' => $product->id, 'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id, 'stock' => $warehouseStock, 'min_stock' => 1,
            'average_cost' => 2, 'last_cost' => 2, 'is_active' => true,
        ]);

        return [$organization, $branch, $product, $warehouse];
    }
}

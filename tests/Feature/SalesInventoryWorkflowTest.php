<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\PosScreen;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Modules\Accounting\Services\SalesAccountingService;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Services\BillingCreditNoteService;
use Modules\Billing\Services\ElectronicBillingService;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryReservation;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Enums\InventoryReservationStatus;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Services\InventoryLedgerBackfillService;
use Modules\Catalog\Services\InventoryMovementService;
use Modules\Catalog\Services\InventoryReservationService;
use Modules\Orders\Entities\Order;
use Modules\Orders\Enums\OrderWarehouseStatus;
use Modules\Orders\Enums\SalesInventoryChannelMode;
use Modules\Orders\Services\OrderCheckoutService;
use Modules\Orders\Services\OrderInventoryLifecycleService;
use Modules\Orders\Services\SalesInventoryChannelRolloutService;
use Modules\Sales\Http\Controllers\SalesPosController;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityAuthorizationService;
use Tests\TestCase;

class SalesInventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecommerce_checkout_reserves_then_dispatch_moves_stock_once(): void
    {
        $scope = $this->scope('ecommerce');
        $checkout = app(OrderCheckoutService::class);
        $payload = [
            'user_id' => $scope['user']->id,
            'idempotency_key' => 'checkout-phase-06',
            'name' => 'Cliente Fase 06',
            'address' => 'Lima',
            'city' => 'Lima',
            'phone' => '999999999',
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'discount' => 999,
            'shipping' => 999,
        ];
        $cart = [(string) $scope['product']->id => ['id' => (string) $scope['product']->id, 'quantity' => 2]];

        $result = $checkout->checkout($payload, $cart);
        $replay = $checkout->checkout($payload, $cart);
        try {
            $checkout->checkout($payload, [(string) $scope['product']->id => ['id' => (string) $scope['product']->id, 'quantity' => 1]]);
            $this->fail('La misma clave con otro carrito debio fallar.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
        }
        $order = $result['order']->fresh(['items', 'inventoryReservation']);

        $this->assertSame($order->id, $replay['order']->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertSame(OrderWarehouseStatus::Reserved, $order->warehouse_status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(0.0, (float) $order->discount);
        $this->assertSame(0.0, (float) $order->shipping);
        $this->assertSame(10, $scope['balance']->fresh()->physical_stock);
        $this->assertSame(2, $scope['balance']->fresh()->reserved_stock);
        $this->assertSame($scope['movement_count'], InventoryMovement::query()->count());

        $lifecycle = app(OrderInventoryLifecycleService::class);
        $draft = $lifecycle->requestDispatch($order, $scope['user']->id);
        $this->assertSame(10, $scope['balance']->fresh()->physical_stock);
        $this->assertSame(2, $scope['balance']->fresh()->reserved_stock);
        $this->assertSame($scope['movement_count'], InventoryMovement::query()->count());

        $dispatched = $lifecycle->confirmDispatch($order, $scope['user']->id);
        $replayedDispatch = $lifecycle->confirmDispatch($order, $scope['user']->id);

        $this->assertSame($dispatched->id, $replayedDispatch->id);
        $this->assertSame(OrderWarehouseStatus::Dispatched, $dispatched->warehouse_status);
        $this->assertSame('fulfilled', $dispatched->status);
        $this->assertSame(8, $scope['balance']->fresh()->physical_stock);
        $this->assertSame(0, $scope['balance']->fresh()->reserved_stock);
        $this->assertSame(InventoryReservationStatus::Consumed, $order->inventoryReservation->fresh()->status);
        $this->assertSame(1, InventoryMovement::query()->where('reference_id', $draft->id)->count());
    }

    public function test_cancel_releases_reservation_without_physical_movement_and_blocks_rollout_downgrade_until_then(): void
    {
        $scope = $this->scope('ecommerce');
        $order = app(OrderCheckoutService::class)->checkout([
            'user_id' => $scope['user']->id,
            'idempotency_key' => 'checkout-cancel-phase-06',
            'name' => 'Cliente', 'address' => 'Lima', 'city' => 'Lima', 'phone' => '999',
        ], [(string) $scope['product']->id => ['id' => (string) $scope['product']->id, 'quantity' => 3]])['order'];

        try {
            $migration = require database_path('migrations/2026_07_16_180000_integrate_sales_inventory_workflow.php');
            $migration->down();
            $this->fail('El rollback con una reserva abierta debio bloquearse.');
        } catch (\RuntimeException) {
            $this->assertTrue(Schema::hasColumn('orders', 'inventory_reservation_id'));
        }

        try {
            app(SalesInventoryChannelRolloutService::class)->setMode(
                $scope['organization']->id,
                'ecommerce',
                SalesInventoryChannelMode::Legacy,
            );
            $this->fail('El downgrade con una reserva abierta debio fallar.');
        } catch (ValidationException) {
            $this->assertSame(3, $scope['balance']->fresh()->reserved_stock);
        }

        $cancelled = app(OrderInventoryLifecycleService::class)->cancel($order, $scope['user']->id);
        $replay = app(OrderInventoryLifecycleService::class)->cancel($order, $scope['user']->id);

        $this->assertSame($cancelled->id, $replay->id);
        $this->assertSame(OrderWarehouseStatus::Released, $cancelled->warehouse_status);
        $this->assertSame(10, $scope['balance']->fresh()->physical_stock);
        $this->assertSame(0, $scope['balance']->fresh()->reserved_stock);
        $this->assertSame($scope['movement_count'], InventoryMovement::query()->count());
        $this->assertSame(InventoryReservationStatus::Released, InventoryReservation::query()->firstOrFail()->status);
    }

    public function test_credit_note_is_fiscal_only_until_warehouse_confirms_customer_return(): void
    {
        $scope = $this->scope('ecommerce');
        $order = app(OrderCheckoutService::class)->checkout([
            'user_id' => $scope['user']->id,
            'idempotency_key' => 'checkout-return-phase-06',
            'name' => 'Cliente', 'address' => 'Lima', 'city' => 'Lima', 'phone' => '999',
        ], [(string) $scope['product']->id => ['id' => (string) $scope['product']->id, 'quantity' => 2]])['order'];
        app(OrderInventoryLifecycleService::class)->confirmDispatch($order, $scope['user']->id);

        $original = BillingDocument::query()->create([
            'organization_id' => $scope['organization']->id,
            'order_id' => $order->id,
            'branch_id' => $scope['branch']->id,
            'provider' => 'demo',
            'document_type' => 'factura',
            'series' => 'F001',
            'number' => '00000001',
            'issue_date' => now()->toDateString(),
            'subtotal' => 21.19,
            'tax' => 3.81,
            'total' => 25,
            'currency' => 'PEN',
            'status' => 'issued',
        ]);
        $creditNote = app(BillingCreditNoteService::class)->create($original, [
            'idempotency_key' => 'credit-note-phase-06',
            'series' => 'FC01',
            'number' => '00000001',
            'reason_code' => '01',
            'reason' => 'Anulacion de la operacion',
        ]);
        $replay = app(BillingCreditNoteService::class)->create($original, [
            'idempotency_key' => 'credit-note-phase-06',
            'series' => 'FC01',
            'number' => '00000001',
            'reason_code' => '01',
            'reason' => 'Anulacion de la operacion',
        ]);
        $this->assertSame($creditNote->id, $replay->id);
        $this->assertSame('draft', $creditNote->status);
        $this->assertSame(8, $scope['balance']->fresh()->physical_stock);
        $this->assertSame($scope['movement_count'] + 1, InventoryMovement::query()->count());

        try {
            app(BillingCreditNoteService::class)->create($original, [
                'idempotency_key' => 'credit-note-overflow-phase-06',
                'series' => 'FC01',
                'number' => '00000002',
                'reason_code' => '01',
                'reason' => 'Segundo credito',
                'total' => 1,
            ]);
            $this->fail('Las notas acumuladas no deben superar el comprobante original.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('billing_documents', 2);
        }

        $unsupportedElectronicIssue = app(ElectronicBillingService::class)->issueOrQueue($creditNote, []);
        $this->assertFalse($unsupportedElectronicIssue['ok']);
        $creditNote = app(BillingCreditNoteService::class)->registerExternalIssuance($creditNote, [
            'provider_reference' => 'EXT-NC-0001',
            'sunat_cdr_code' => '0',
        ]);
        $this->assertSame('issued', $creditNote->status);
        $returnDraft = app(OrderInventoryLifecycleService::class)->requestReturn($order, $creditNote->id, $scope['user']->id);
        $this->assertSame(8, $scope['balance']->fresh()->physical_stock);
        $this->assertSame($scope['movement_count'] + 1, InventoryMovement::query()->count());

        $returned = app(OrderInventoryLifecycleService::class)->confirmReturn($order, $creditNote->id, $scope['user']->id);
        $replayedReturn = app(OrderInventoryLifecycleService::class)->confirmReturn($order, $creditNote->id, $scope['user']->id);
        $this->assertSame($returned->id, $replayedReturn->id);
        $this->assertSame(OrderWarehouseStatus::Returned, $returned->warehouse_status);
        $this->assertSame(10, $scope['balance']->fresh()->physical_stock);
        $this->assertSame($scope['movement_count'] + 2, InventoryMovement::query()->count());
        $this->assertSame($returnDraft->id, $returned->return_document_id);
    }

    public function test_pos_repeated_lines_create_one_order_and_one_reservation_without_stock_exit(): void
    {
        $scope = $this->scope('pos');
        $payload = [
            'document_type' => 'order',
            'currency' => 'PEN',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'idempotency_key' => 'pos-phase-06',
            'customer' => ['name' => 'Cliente POS'],
            'items' => [
                ['product_id' => $scope['product']->id, 'quantity' => 1, 'unit_price' => 12.50],
                ['product_id' => $scope['product']->id, 'quantity' => 2, 'unit_price' => 12.50],
            ],
        ];

        $this->storePos($scope['user'], $payload);
        $this->storePos($scope['user'], $payload);

        $order = Order::query()->with('items')->firstOrFail();
        $this->assertSame('pos', $order->sales_channel);
        $this->assertSame(OrderWarehouseStatus::Reserved, $order->warehouse_status);
        $this->assertCount(1, $order->items);
        $this->assertSame(3, (int) $order->items->first()->quantity);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertSame(10, $scope['balance']->fresh()->physical_stock);
        $this->assertSame(3, $scope['balance']->fresh()->reserved_stock);
        $this->assertSame($scope['movement_count'], InventoryMovement::query()->count());
    }

    public function test_pos_reserves_selected_non_default_warehouse(): void
    {
        $scope = $this->scope('pos');
        $authorization = Mockery::mock(SecurityAuthorizationService::class);
        $authorization->shouldReceive('hasRole')->andReturn(true);
        app()->instance(SecurityAuthorizationService::class, $authorization);
        $alternate = InventoryWarehouse::query()->create([
            'organization_id' => $scope['organization']->id,
            'branch_id' => $scope['branch']->id,
            'code' => 'POS-ALT', 'name' => 'Almacén alterno',
            'is_default' => false, 'is_active' => true,
        ]);
        ProductWarehouseStock::query()->create([
            'organization_id' => $scope['organization']->id,
            'product_id' => $scope['product']->id,
            'branch_id' => $scope['branch']->id,
            'warehouse_id' => $alternate->id,
            'stock' => 0, 'is_active' => true,
        ]);
        app(InventoryMovementService::class)->recordWarehouseOpeningStock(
            $scope['product'], $scope['branch']->id, $alternate->id, 4,
            ['idempotency_key' => 'pos-alternate-opening'],
        );

        $this->storePos($scope['user'], [
            'document_type' => 'order', 'currency' => 'PEN',
            'payment_method' => 'cash', 'payment_status' => 'pending',
            'idempotency_key' => 'pos-alternate-sale',
            'branch_id' => $scope['branch']->id, 'warehouse_id' => $alternate->id,
            'customer' => ['name' => 'Cliente alterno'],
            'items' => [['product_id' => $scope['product']->id, 'quantity' => 2, 'unit_price' => 12.50]],
        ]);

        $order = Order::query()->firstOrFail();
        $alternateBalance = InventoryBalance::query()->where('warehouse_id', $alternate->id)->firstOrFail();
        $this->assertSame($alternate->id, (int) $order->warehouse_id);
        $this->assertSame(2, (int) $alternateBalance->reserved_stock);
        $this->assertSame(0, (int) $scope['balance']->fresh()->reserved_stock);
        $this->assertSame($alternate->id, (int) $order->items()->firstOrFail()->warehouse_id);

        try {
            $this->storePos($scope['user'], [
                'document_type' => 'order', 'currency' => 'PEN',
                'payment_method' => 'cash', 'payment_status' => 'pending',
                'idempotency_key' => 'pos-alternate-overflow',
                'branch_id' => $scope['branch']->id, 'warehouse_id' => $alternate->id,
                'customer' => ['name' => 'Cliente sin stock alterno'],
                'items' => [['product_id' => $scope['product']->id, 'quantity' => 3, 'unit_price' => 12.50]],
            ]);
            $this->fail('La venta no debe usar stock del almacén predeterminado.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
            $this->assertSame(2, (int) $alternateBalance->fresh()->reserved_stock);
        }
    }

    public function test_pos_preserves_typed_fractional_service_quantity_and_zero_price(): void
    {
        $scope = $this->scope('pos');
        $service = Product::query()->create([
            'organization_id' => $scope['organization']->id,
            'category_id' => $scope['category']->id,
            'name' => 'Servicio fraccionable',
            'sku' => 'F6-S-'.uniqid(),
            'slug' => 'servicio-f6-'.uniqid(),
            'tax_affectation' => 'Gravado',
            'product_type' => ProductType::Service->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 20,
            'sale_price' => 20,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $this->storePos($scope['user'], [
            'document_type' => 'order',
            'currency' => 'PEN',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'idempotency_key' => 'pos-service-fraction',
            'tax_rate' => 0,
            'customer' => ['name' => 'Cliente servicio'],
            'items' => [
                ['product_id' => $service->id, 'quantity' => '0.5', 'unit_price' => '12.50'],
                ['product_id' => $scope['product']->id, 'quantity' => '1', 'unit_price' => '0'],
            ],
        ]);

        $order = Order::query()->with('items')->firstOrFail();
        $serviceLine = $order->items->firstWhere('product_id', $service->id);
        $physicalLine = $order->items->firstWhere('product_id', $scope['product']->id);
        $this->assertSame(0.5, (float) $serviceLine->quantity);
        $this->assertSame(6.25, (float) $serviceLine->line_total);
        $this->assertSame(0.0, (float) $physicalLine->unit_price);
        $this->assertSame(0.0, (float) $physicalLine->line_total);
        $this->assertSame(1, $scope['balance']->fresh()->reserved_stock);
    }

    public function test_pos_rejects_fractional_quantity_for_inventory_product(): void
    {
        $scope = $this->scope('pos');

        $this->expectException(ValidationException::class);
        $this->storePos($scope['user'], [
            'document_type' => 'order',
            'currency' => 'PEN',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'idempotency_key' => 'pos-physical-fraction',
            'customer' => ['name' => 'Cliente inventario'],
            'items' => [
                ['product_id' => $scope['product']->id, 'quantity' => '0.5', 'unit_price' => '12.50'],
                ['product_id' => $scope['product']->id, 'quantity' => '0.5', 'unit_price' => '12.50'],
            ],
        ]);
    }

    public function test_pos_uses_catalog_price_when_manual_price_is_empty(): void
    {
        $scope = $this->scope('pos');

        $this->storePos($scope['user'], [
            'document_type' => 'order',
            'currency' => 'PEN',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'idempotency_key' => 'pos-empty-manual-price',
            'customer' => ['name' => 'Cliente POS'],
            'items' => [
                ['product_id' => $scope['product']->id, 'quantity' => '1', 'unit_price' => ''],
            ],
        ]);

        $this->assertSame(12.5, (float) Order::query()->firstOrFail()->items()->firstOrFail()->unit_price);
    }

    public function test_pos_screen_validates_quantities_and_renders_all_steps(): void
    {
        $scope = $this->scope('pos');
        $authorization = Mockery::mock(SecurityAuthorizationService::class);
        $authorization->shouldReceive('hasRole')->andReturn(true);
        $authorization->shouldReceive('canAccessModule')->andReturn(true);
        $authorization->shouldReceive('hasPermission')->andReturn(false);
        app()->instance(SecurityAuthorizationService::class, $authorization);
        config()->set('app.key', str_repeat('x', 32));

        Livewire::actingAs($scope['user'])
            ->test(PosScreen::class)
            ->assertSee('Seleccion de productos')
            ->set('productIndex', [[
                'id' => $scope['product']->id,
                'name' => $scope['product']->name,
                'sku' => $scope['product']->sku,
                'label' => $scope['product']->name,
                'stock' => 10,
                'price' => 12.5,
                'tracks_inventory' => true,
            ]])
            ->set('items.0.product_id', (string) $scope['product']->id)
            ->assertSet('items.0.unit_price', '12.50')
            ->set('items.0.quantity', '0.5')
            ->call('goNext')
            ->assertSet('currentStep', 0)
            ->assertHasErrors(['wizard'])
            ->set('items.0.quantity', '1')
            ->call('goNext')
            ->assertSet('currentStep', 1)
            ->assertSee('Informacion del cliente')
            ->set('customer.name', 'Cliente POS')
            ->call('goNext')
            ->assertSet('currentStep', 2)
            ->assertSee('Pago y cierre')
            ->set('taxRate', 'abc')
            ->assertHasErrors(['taxRate'])
            ->set('taxRate', '0.18')
            ->assertHasNoErrors(['taxRate'])
            ->set('discount', '12.50')
            ->assertSet('discount', '12.50')
            ->set('shipping', '0.25')
            ->assertSet('shipping', '0.25');
    }

    public function test_mixed_checkout_reserves_only_inventory_products(): void
    {
        $scope = $this->scope('ecommerce');
        $service = Product::query()->create([
            'organization_id' => $scope['organization']->id,
            'category_id' => $scope['category']->id,
            'name' => 'Servicio F6',
            'sku' => 'F6-S-'.uniqid(),
            'slug' => 'servicio-f6-'.uniqid(),
            'tax_affectation' => 'Gravado',
            'product_type' => ProductType::Service->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 20,
            'sale_price' => 20,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        $order = app(OrderCheckoutService::class)->checkout([
            'user_id' => $scope['user']->id,
            'idempotency_key' => 'checkout-mixed-phase-06',
            'name' => 'Cliente', 'address' => 'Lima', 'city' => 'Lima', 'phone' => '999',
        ], [
            (string) $scope['product']->id => ['id' => (string) $scope['product']->id, 'quantity' => 2],
            (string) $service->id => ['id' => (string) $service->id, 'quantity' => 5],
        ])['order']->fresh(['items', 'inventoryReservation.items']);

        $this->assertCount(2, $order->items);
        $this->assertCount(1, $order->inventoryReservation->items);
        $this->assertSame($scope['product']->id, $order->inventoryReservation->items->first()->product_id);
        $this->assertSame(2, $scope['balance']->fresh()->reserved_stock);
        $this->assertSame(10, $scope['balance']->fresh()->physical_stock);
    }

    public function test_expired_order_reservation_updates_state_and_can_be_renewed(): void
    {
        $scope = $this->scope('ecommerce');
        $order = app(OrderCheckoutService::class)->checkout([
            'user_id' => $scope['user']->id,
            'idempotency_key' => 'checkout-expiry-phase-06',
            'name' => 'Cliente', 'address' => 'Lima', 'city' => 'Lima', 'phone' => '999',
        ], [(string) $scope['product']->id => ['id' => (string) $scope['product']->id, 'quantity' => 3]])['order'];
        $reservation = $order->inventoryReservation()->firstOrFail();
        $reservation->forceFill(['expires_at' => now()->subMinute()])->save();

        app(InventoryReservationService::class)->expire(
            $scope['organization']->id,
            $reservation->id,
            'expire-order-phase-06',
            $scope['user']->id,
        );
        $order->refresh();
        $this->assertSame(OrderWarehouseStatus::ReservationExpired, $order->warehouse_status);
        $this->assertSame(0, $scope['balance']->fresh()->reserved_stock);
        $this->assertSame(0, (int) $order->items()->sum('reserved_quantity'));

        $renewed = app(OrderInventoryLifecycleService::class)->reserve($order, $scope['user']->id);
        $this->assertSame(OrderWarehouseStatus::Reserved, $renewed->warehouse_status);
        $this->assertSame(2, $renewed->reservation_version);
        $this->assertNotSame($reservation->id, $renewed->inventory_reservation_id);
        $this->assertSame(3, $scope['balance']->fresh()->reserved_stock);
    }

    public function test_partial_credit_note_cannot_trigger_a_full_physical_return(): void
    {
        $scope = $this->scope('ecommerce');
        $order = app(OrderCheckoutService::class)->checkout([
            'user_id' => $scope['user']->id,
            'idempotency_key' => 'checkout-partial-credit-phase-06',
            'name' => 'Cliente', 'address' => 'Lima', 'city' => 'Lima', 'phone' => '999',
        ], [(string) $scope['product']->id => ['id' => (string) $scope['product']->id, 'quantity' => 2]])['order'];
        app(OrderInventoryLifecycleService::class)->confirmDispatch($order, $scope['user']->id);
        $original = BillingDocument::query()->create([
            'organization_id' => $scope['organization']->id, 'order_id' => $order->id, 'branch_id' => $scope['branch']->id,
            'provider' => 'external', 'document_type' => 'factura', 'series' => 'F001', 'number' => '00000009',
            'issue_date' => now()->toDateString(), 'subtotal' => 21.19, 'tax' => 3.81, 'total' => 25,
            'currency' => 'PEN', 'status' => 'issued',
        ]);
        $creditNote = app(BillingCreditNoteService::class)->create($original, [
            'idempotency_key' => 'partial-credit-phase-06', 'series' => 'FC01', 'number' => '00000009',
            'reason_code' => '07', 'reason' => 'Devolucion parcial', 'total' => 10,
        ]);
        $creditNote = app(BillingCreditNoteService::class)->registerExternalIssuance($creditNote, [
            'provider_reference' => 'EXT-NC-PARTIAL-09',
        ]);

        try {
            app(OrderInventoryLifecycleService::class)->requestReturn($order, $creditNote->id, $scope['user']->id);
            $this->fail('Una nota parcial no debe reingresar todo el pedido.');
        } catch (ValidationException) {
            $this->assertSame(8, $scope['balance']->fresh()->physical_stock);
            $this->assertSame($scope['movement_count'] + 1, InventoryMovement::query()->count());
            $this->assertNull($order->fresh()->return_document_id);
        }
    }

    /** @return array<string, mixed> */
    private function scope(string $channel): array
    {
        $organization = Organization::query()->create([
            'code' => 'F6-'.uniqid(), 'name' => 'Phase 06', 'slug' => 'phase06-'.uniqid(),
            'status' => 'active', 'environment' => 'demo', 'is_default' => true,
        ]);
        $branch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => 'F6-B-'.uniqid(), 'name' => 'Principal',
            'is_active' => true, 'is_default' => true,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'branch_id' => $branch->id]);
        $this->actingAs($user);
        $category = Category::query()->create([
            'organization_id' => $organization->id, 'name' => 'Fisicos', 'slug' => 'fisicos-'.uniqid(),
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Producto F6',
            'sku' => 'F6-P-'.uniqid(), 'slug' => 'producto-f6-'.uniqid(), 'tax_affectation' => 'Gravado',
            'product_type' => ProductType::PhysicalGood->value, 'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 12.50, 'sale_price' => 12.50, 'purchase_price' => 5, 'average_price' => 5,
            'stock' => 10, 'min_stock' => 0, 'is_active' => true,
        ]);
        $warehouse = InventoryWarehouse::query()->create([
            'organization_id' => $organization->id, 'branch_id' => $branch->id, 'code' => 'F6-W-'.uniqid(),
            'name' => 'Principal', 'is_default' => true, 'is_active' => true,
        ]);
        ProductBranchStock::query()->create([
            'organization_id' => $organization->id, 'product_id' => $product->id, 'branch_id' => $branch->id,
            'stock' => 10, 'min_stock' => 0, 'is_active' => true,
        ]);
        ProductWarehouseStock::query()->create([
            'organization_id' => $organization->id, 'product_id' => $product->id, 'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id, 'stock' => 10, 'min_stock' => 0,
            'average_cost' => 5, 'last_cost' => 5, 'is_active' => true,
        ]);
        app(InventoryLedgerBackfillService::class)->run($organization->id);
        InventoryLedgerRollout::query()->create([
            'organization_id' => $organization->id,
            'mode' => InventoryLedgerRolloutMode::Active->value,
            'reconciled_at' => now(),
            'activated_at' => now(),
        ]);
        app(SalesInventoryChannelRolloutService::class)->setMode(
            $organization->id,
            $channel,
            SalesInventoryChannelMode::Active,
        );

        return [
            'organization' => $organization,
            'branch' => $branch,
            'user' => $user,
            'product' => $product,
            'category' => $category,
            'warehouse' => $warehouse,
            'balance' => InventoryBalance::query()->where('warehouse_id', $warehouse->id)->firstOrFail(),
            'movement_count' => InventoryMovement::query()->count(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function storePos(User $user, array $payload): void
    {
        $request = Request::create('/admin/sales/pos', 'POST', $payload);
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);

        app(SalesPosController::class)->store(
            $request,
            app(ElectronicBillingService::class),
            app(SalesAccountingService::class),
        );
    }
}

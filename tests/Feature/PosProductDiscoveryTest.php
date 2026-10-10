<?php

namespace Tests\Feature;

use App\Livewire\Admin\PosScreen;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Modules\Accounting\Services\SalesAccountingService;
use Modules\Billing\Models\BillingSetting;
use Modules\Billing\Services\ElectronicBillingService;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Entities\UnitMeasure;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Orders\Entities\Order;
use Modules\Sales\Http\Controllers\SalesPosController;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityAuthorizationService;
use Tests\TestCase;

class PosProductDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_creates_product_and_loads_stock_without_losing_sale(): void
    {
        $fixture = $this->fixture();
        $this->authorizeActions(true);

        $sale = Livewire::actingAs($fixture['user'])
            ->test(PosScreen::class)
            ->set('items.0.product_id', (string) $fixture['service']->id)
            ->call('openQuickProduct')
            ->set('quickProduct.name', 'Café nuevo')
            ->set('quickProduct.sku', 'CAF-NUEVO')
            ->set('quickProduct.brand', 'Andes')
            ->set('quickProduct.category_id', (string) $fixture['category']->id)
            ->set('quickProduct.unit_measure_id', (string) $fixture['unit']->id)
            ->set('quickProduct.product_type', ProductType::PhysicalGood->value)
            ->set('quickProduct.sale_price', '12.50')
            ->set('quickProduct.purchase_price', '5.25')
            ->call('saveQuickProduct')
            ->assertHasNoErrors()
            ->assertSet('quickProductStep', 2)
            ->assertSet('items.0.product_id', (string) $fixture['service']->id);

        $newProduct = Product::query()->where('sku', 'CAF-NUEVO')->firstOrFail();
        $this->assertSame('Andes', $newProduct->brand);
        $this->assertSame(0, InventoryDocument::query()->count());

        $sale
            ->set('quickStockWarehouseId', (string) $fixture['warehouse']->id)
            ->set('quickStockQuantity', '3')
            ->set('quickStockUnitCost', '5.25')
            ->call('saveQuickStock')
            ->assertHasNoErrors()
            ->assertSet('items.0.product_id', (string) $fixture['service']->id)
            ->assertSet('items.1.product_id', (string) $newProduct->id);

        $this->assertDatabaseHas('inventory_document_items', [
            'product_id' => $newProduct->id,
        ]);
        $this->assertSame(1, InventoryDocument::query()->count());
    }

    public function test_invoice_uses_warehouse_stock_loaded_from_quick_product_flow(): void
    {
        $fixture = $this->fixture();
        $this->authorizeActions(true);
        $alternate = InventoryWarehouse::query()->create([
            'organization_id' => $fixture['organization']->id,
            'branch_id' => $fixture['branch']->id,
            'code' => 'SECOND', 'name' => 'Almacén secundario',
            'is_default' => false, 'is_active' => true,
        ]);

        Livewire::actingAs($fixture['user'])
            ->test(PosScreen::class)
            ->assertSet('warehouseId', (string) $fixture['warehouse']->id)
            ->set('warehouseId', (string) $alternate->id)
            ->call('openQuickProduct')
            ->set('quickProduct.name', 'Producto facturable')
            ->set('quickProduct.category_id', (string) $fixture['category']->id)
            ->set('quickProduct.unit_measure_id', (string) $fixture['unit']->id)
            ->set('quickProduct.sale_price', '12.50')
            ->call('saveQuickProduct')
            ->assertSet('quickStockWarehouseId', (string) $alternate->id)
            ->set('quickStockQuantity', '3')
            ->set('quickStockUnitCost', '5.25')
            ->call('saveQuickStock')
            ->assertHasNoErrors();

        $product = Product::query()->where('name', 'Producto facturable')->firstOrFail();
        BillingSetting::query()->create([
            'organization_id' => $fixture['organization']->id,
            'enabled' => true,
            'provider' => 'external',
            'invoice_series' => 'F001',
        ]);
        $billing = Mockery::mock(ElectronicBillingService::class);
        $billing->shouldReceive('issueOrQueue')->once()->andReturn([
            'ok' => false, 'queued' => false, 'message' => 'Pendiente de emisión',
        ]);
        $request = Request::create('/admin/sales/pos', 'POST', [
            'document_type' => 'factura',
            'currency' => 'PEN',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'idempotency_key' => 'pos-quick-stock-invoice',
            'branch_id' => $fixture['branch']->id,
            'warehouse_id' => $alternate->id,
            'customer' => ['name' => 'Cliente facturable', 'document_type' => 'RUC', 'document_number' => '20123456789'],
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_price' => '12.50']],
        ]);
        $request->setUserResolver(fn () => $fixture['user']);
        $this->actingAs($fixture['user']);
        app()->instance('request', $request);

        app(SalesPosController::class)->store($request, $billing, app(SalesAccountingService::class));
        app(SalesPosController::class)->store($request, $billing, app(SalesAccountingService::class));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('billing_documents', 1);
        $this->assertSame($alternate->id, (int) Order::query()->value('warehouse_id'));
        $this->assertSame(2, (int) InventoryBalance::query()
            ->where('product_id', $product->id)->where('warehouse_id', $alternate->id)
            ->value('physical_stock'));
        $this->assertSame(1, InventoryMovement::query()
            ->where('product_id', $product->id)->where('reference_type', Order::class)
            ->where('warehouse_id', $alternate->id)->count());
    }

    public function test_product_and_stock_actions_reject_users_without_permissions(): void
    {
        $fixture = $this->fixture();
        $this->authorizeActions(false);

        Livewire::actingAs($fixture['user'])
            ->test(PosScreen::class)
            ->call('openQuickProduct')
            ->assertForbidden();

        Livewire::actingAs($fixture['user'])
            ->test(PosScreen::class)
            ->call('saveQuickStock')
            ->assertForbidden();

        $this->actingAs($fixture['user'])->get(route('admin.products.create'))->assertForbidden();
        $this->actingAs($fixture['user'])->post(route('admin.products.store'), [])->assertForbidden();
    }

    public function test_changing_sale_branch_updates_warehouse_and_stock_without_losing_items(): void
    {
        $fixture = $this->fixture();
        $this->authorizeActions(true);
        $otherBranch = SecurityBranch::query()->create([
            'organization_id' => $fixture['organization']->id,
            'code' => 'SECOND', 'name' => 'Secundaria', 'is_active' => true, 'is_default' => false,
        ]);
        $otherWarehouse = InventoryWarehouse::query()->create([
            'organization_id' => $fixture['organization']->id,
            'branch_id' => $otherBranch->id,
            'code' => 'SECOND', 'name' => 'Secundario', 'is_active' => true, 'is_default' => true,
        ]);
        $product = Product::query()->create([
            'organization_id' => $fixture['organization']->id,
            'category_id' => $fixture['category']->id,
            'unit_measure_id' => $fixture['unit']->id,
            'name' => 'Producto por sucursal', 'sku' => 'BRANCH-PRODUCT', 'slug' => 'producto-por-sucursal',
            'tax_affectation' => 'Gravado', 'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 8, 'sale_price' => 8, 'stock' => 2, 'is_active' => true,
        ]);
        ProductBranchStock::query()->create([
            'organization_id' => $fixture['organization']->id, 'product_id' => $product->id,
            'branch_id' => $fixture['branch']->id, 'stock' => 2, 'is_active' => true,
        ]);
        ProductWarehouseStock::query()->create([
            'organization_id' => $fixture['organization']->id, 'product_id' => $product->id,
            'branch_id' => $fixture['branch']->id, 'warehouse_id' => $fixture['warehouse']->id,
            'stock' => 2, 'is_active' => true,
        ]);

        Livewire::actingAs($fixture['user'])->test(PosScreen::class)
            ->set('items.0.product_id', (string) $product->id)
            ->set('customer.name', 'Cliente conservado')
            ->set('branchId', (string) $otherBranch->id)
            ->assertSet('warehouseId', (string) $otherWarehouse->id)
            ->assertSet('items.0.product_id', (string) $product->id)
            ->assertSet('customer.name', 'Cliente conservado')
            ->assertSet('productIndex', fn (array $index): bool => collect($index)->firstWhere('id', $product->id)['stock'] === 0)
            ->call('goNext')->assertHasErrors(['wizard'])
            ->set('branchId', (string) $fixture['branch']->id)
            ->assertSet('warehouseId', (string) $fixture['warehouse']->id)
            ->assertSet('productIndex', fn (array $index): bool => collect($index)->firstWhere('id', $product->id)['stock'] === 2);
    }

    public function test_sale_rejects_warehouse_from_another_branch(): void
    {
        $fixture = $this->fixture();
        $this->authorizeActions(true);
        $otherBranch = SecurityBranch::query()->create([
            'organization_id' => $fixture['organization']->id,
            'code' => 'OTHER', 'name' => 'Otra', 'is_active' => true,
        ]);
        $otherWarehouse = InventoryWarehouse::query()->create([
            'organization_id' => $fixture['organization']->id,
            'branch_id' => $otherBranch->id,
            'code' => 'OTHER', 'name' => 'Ajeno', 'is_active' => true,
        ]);
        $request = Request::create('/admin/sales/pos', 'POST', [
            'document_type' => 'order', 'currency' => 'PEN',
            'payment_method' => 'cash', 'payment_status' => 'pending',
            'idempotency_key' => 'pos-wrong-warehouse',
            'branch_id' => $fixture['branch']->id,
            'warehouse_id' => $otherWarehouse->id,
            'customer' => ['name' => 'Cliente'],
            'items' => [['product_id' => $fixture['service']->id, 'quantity' => '1', 'unit_price' => '10.00']],
        ]);
        $request->setUserResolver(fn () => $fixture['user']);
        $this->actingAs($fixture['user']);
        app()->instance('request', $request);

        try {
            app(SalesPosController::class)->store($request, app(ElectronicBillingService::class), app(SalesAccountingService::class));
            $this->fail('El almacén de otra sucursal no debe aceptarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('warehouse_id', $exception->errors());
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_new_service_is_added_immediately_without_inventory_document(): void
    {
        $fixture = $this->fixture();
        $this->authorizeActions(true);

        Livewire::actingAs($fixture['user'])
            ->test(PosScreen::class)
            ->set('items.0.product_id', (string) $fixture['service']->id)
            ->call('openQuickProduct')
            ->set('quickProduct.name', 'Instalación rápida')
            ->set('quickProduct.category_id', (string) $fixture['category']->id)
            ->set('quickProduct.unit_measure_id', (string) $fixture['unit']->id)
            ->set('quickProduct.product_type', ProductType::Service->value)
            ->set('quickProduct.sale_price', '8.50')
            ->call('saveQuickProduct')
            ->assertHasNoErrors()
            ->assertSet('quickProductOpen', false)
            ->assertSet('items.0.product_id', (string) $fixture['service']->id)
            ->assertSet('items.1.product_id', (string) Product::query()->where('name', 'Instalación rápida')->firstOrFail()->id);

        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_search_suggests_catalog_matches_and_advanced_view_finds_unstocked_products(): void
    {
        $fixture = $this->fixture();
        $this->authorizeActions(true);
        Product::query()->create([
            'organization_id' => $fixture['organization']->id,
            'category_id' => $fixture['category']->id,
            'unit_measure_id' => $fixture['unit']->id,
            'name' => 'Café sin stock', 'sku' => 'CAF-09', 'slug' => 'cafe-sin-stock',
            'brand' => 'Andes', 'description' => 'Tueste especial',
            'tax_affectation' => 'Gravado', 'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 14, 'sale_price' => 14, 'stock' => 0, 'is_active' => true,
        ]);

        Livewire::actingAs($fixture['user'])
            ->test(PosScreen::class)
            ->set('productSearch', 'CAF-09')
            ->assertSee('Café sin stock')
            ->call('openAdvancedSearch')
            ->set('advancedFilters.stock', 'out')
            ->set('advancedFilters.brand', 'Andes')
            ->set('advancedFilters.description', 'Tueste')
            ->assertSee('Café sin stock')
            ->assertSee('Cargar stock');
    }

    public function test_physical_product_can_wait_for_stock_without_changing_sale_items(): void
    {
        $fixture = $this->fixture();
        $this->authorizeActions(true);

        Livewire::actingAs($fixture['user'])
            ->test(PosScreen::class)
            ->set('items.0.product_id', (string) $fixture['service']->id)
            ->call('openQuickProduct')
            ->set('quickProduct.name', 'Producto pendiente')
            ->set('quickProduct.category_id', (string) $fixture['category']->id)
            ->set('quickProduct.unit_measure_id', (string) $fixture['unit']->id)
            ->set('quickProduct.sale_price', '9.00')
            ->call('saveQuickProduct')
            ->assertHasNoErrors()
            ->call('skipQuickStock')
            ->assertSet('quickProductOpen', false)
            ->assertSet('items.0.product_id', (string) $fixture['service']->id)
            ->assertCount('items', 1);

        $this->assertDatabaseHas('products', ['name' => 'Producto pendiente', 'stock' => 0]);
        $this->assertDatabaseCount('inventory_documents', 0);
    }

    private function authorizeActions(bool $allow): void
    {
        $authorization = Mockery::mock(SecurityAuthorizationService::class);
        $authorization->shouldReceive('hasRole')->andReturn(true);
        $authorization->shouldReceive('canAccessModule')->andReturn(true);
        $authorization->shouldReceive('hasPermission')->andReturn($allow);
        app()->instance(SecurityAuthorizationService::class, $authorization);
    }

    private function fixture(): array
    {
        $organization = Organization::query()->create([
            'code' => 'POS-DISCOVERY', 'name' => 'POS Discovery', 'slug' => 'pos-discovery',
            'status' => 'active', 'environment' => 'demo', 'is_default' => true,
        ]);
        $branch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => 'MAIN', 'name' => 'Principal',
            'is_active' => true, 'is_default' => true,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'branch_id' => $branch->id]);
        $category = Category::query()->create([
            'organization_id' => $organization->id, 'name' => 'Bebidas', 'slug' => 'bebidas',
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $unit = UnitMeasure::query()->create(['organization_id' => $organization->id, 'name' => 'Unidad']);
        $warehouse = InventoryWarehouse::query()->create([
            'organization_id' => $organization->id, 'branch_id' => $branch->id,
            'code' => 'MAIN', 'name' => 'Principal', 'is_default' => true, 'is_active' => true,
        ]);
        $service = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id,
            'unit_measure_id' => $unit->id, 'name' => 'Servicio actual',
            'sku' => 'SERVICE-1', 'slug' => 'servicio-actual',
            'tax_affectation' => 'Gravado', 'product_type' => ProductType::Service->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 10, 'sale_price' => 10, 'stock' => 0, 'is_active' => true,
        ]);

        return compact('organization', 'branch', 'user', 'category', 'unit', 'warehouse', 'service');
    }
}

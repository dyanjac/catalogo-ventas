<?php

namespace Tests\Feature;

use App\Livewire\Admin\PosScreen;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\UnitMeasure;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
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

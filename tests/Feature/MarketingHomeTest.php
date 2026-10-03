<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Security\Models\SecurityRole;
use Tests\TestCase;

class MarketingHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_renders_the_erp_home_without_storefront_products_or_tenant_branding(): void
    {
        $organization = $this->createOrganization('TIENDA-UNO', 'tienda-uno');

        CommerceSetting::query()->create([
            'organization_id' => $organization->id,
            'brand_name' => 'Marca de la tienda',
            'company_name' => 'Tienda Uno SAC',
            'email' => 'tienda@ejemplo.test',
        ]);

        $category = Category::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Categoría de prueba',
            'slug' => 'categoria-de-prueba',
        ]);

        Product::query()->create([
            'organization_id' => $organization->id,
            'category_id' => $category->id,
            'name' => 'Producto exclusivo de la tienda',
            'slug' => 'producto-exclusivo',
            'price' => 10,
            'is_active' => true,
        ]);

        $this->withSession(['organization_context_slug' => $organization->slug])
            ->get('/')
            ->assertOk()
            ->assertViewIs('marketing.home')
            ->assertSee('Conecta tus ventas con toda tu operación.')
            ->assertDontSee('Producto exclusivo de la tienda')
            ->assertDontSee('Marca de la tienda')
            ->assertDontSee('Comprar ahora');
    }

    public function test_authenticated_customer_is_redirected_to_their_storefront(): void
    {
        $organization = $this->createOrganization('CLIENTE-UNO', 'cliente-uno');
        app(OrganizationEntitlementService::class)->assignDefaultPlan($organization);

        $customer = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->get('/login')
            ->assertRedirect(route('ecommerce.home', ['commerce' => $organization->slug]));

        $this->post('/logout')
            ->assertRedirect(route('ecommerce.home', ['commerce' => $organization->slug]));
    }

    public function test_authenticated_administrator_is_redirected_to_the_dashboard(): void
    {
        $organization = $this->createOrganization('ADMIN-UNO', 'admin-uno');
        $administrator = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $role = SecurityRole::query()->create([
            'code' => 'super_admin',
            'name' => 'Super administrador',
            'is_system' => true,
            'is_active' => true,
        ]);
        $administrator->roles()->attach($role->id, [
            'scope' => 'all',
            'is_active' => true,
        ]);

        $this->actingAs($administrator)
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    private function createOrganization(string $code, string $slug): Organization
    {
        return Organization::query()->create([
            'code' => $code,
            'name' => "Organization {$code}",
            'slug' => $slug,
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => false,
            'settings_json' => [],
        ]);
    }
}

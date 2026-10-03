<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContextService;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\UnitMeasure;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Commerce\Entities\SaasCapability;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Commerce\Services\StorefrontRouteService;
use Tests\TestCase;

class PublicStorefrontContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('public.storefront')->get('/_test/storefront/{commerce}', function (OrganizationContextService $context) {
            return response()->json([
                'organization_id' => $context->currentOrganizationId(),
                'organization_slug' => $context->current()?->slug,
            ]);
        });
    }

    public function test_active_tenant_with_ecommerce_capability_becomes_the_public_context(): void
    {
        $organization = $this->createOrganization('PUBLIC-STORE');
        app(OrganizationEntitlementService::class)->assignDefaultPlan($organization);

        $this->get('/_test/storefront/public-store')
            ->assertOk()
            ->assertJson([
                'organization_id' => $organization->id,
                'organization_slug' => 'public-store',
            ]);
    }

    public function test_public_storefront_context_overrides_the_authenticated_users_tenant(): void
    {
        $accountOrganization = $this->createOrganization('ACCOUNT');
        $storefrontOrganization = $this->createOrganization('STOREFRONT');
        app(OrganizationEntitlementService::class)->assignDefaultPlan($storefrontOrganization);

        $user = User::factory()->create(['organization_id' => $accountOrganization->id]);

        $this->actingAs($user)
            ->get('/_test/storefront/storefront')
            ->assertOk()
            ->assertJson([
                'organization_id' => $storefrontOrganization->id,
                'organization_slug' => 'storefront',
            ]);
    }

    public function test_public_storefront_routes_render_the_selected_tenants_branding(): void
    {
        $organization = $this->createOrganization('BRANDED-STORE');
        app(OrganizationEntitlementService::class)->assignDefaultPlan($organization);

        CommerceSetting::query()->create([
            'organization_id' => $organization->id,
            'brand_name' => 'Tienda Branded',
            'company_name' => 'Tienda Branded SAC',
            'email' => 'ventas@branded.test',
        ]);

        $this->get('/ecommerce/branded-store')
            ->assertOk()
            ->assertViewIs('storefront.home')
            ->assertSee('Tienda Branded')
            ->assertSee('/ecommerce/branded-store/catalogo', false);

        $this->get('/ecommerce/branded-store/catalogo')->assertOk();
    }

    public function test_each_public_storefront_reads_only_its_own_cart(): void
    {
        $organizationA = $this->createOrganization('CART-A');
        $organizationB = $this->createOrganization('CART-B');
        $entitlements = app(OrganizationEntitlementService::class);
        $entitlements->assignDefaultPlan($organizationA);
        $entitlements->assignDefaultPlan($organizationB);

        $session = [
            'cart' => [
                'legacy' => ['id' => 'legacy', 'name' => 'Producto legacy', 'price' => 1, 'quantity' => 1, 'image' => null],
            ],
            "ecommerce.cart.{$organizationA->id}" => [
                'a' => ['id' => 'a', 'name' => 'Producto del comercio A', 'price' => 10, 'quantity' => 2, 'image' => null],
            ],
            "ecommerce.cart.{$organizationB->id}" => [
                'b' => ['id' => 'b', 'name' => 'Producto del comercio B', 'price' => 15, 'quantity' => 3, 'image' => null],
            ],
        ];

        $this->withSession($session)
            ->get('/ecommerce/cart-a/carrito')
            ->assertOk()
            ->assertSee('Producto del comercio A')
            ->assertDontSee('Producto del comercio B')
            ->assertDontSee('Producto legacy');

        $this->withSession($session)
            ->get('/ecommerce/cart-b/carrito')
            ->assertOk()
            ->assertSee('Producto del comercio B')
            ->assertDontSee('Producto del comercio A')
            ->assertDontSee('Producto legacy');
    }

    public function test_storefront_cannot_resolve_a_product_from_another_organization(): void
    {
        $storefrontOrganization = $this->createOrganization('PRODUCT-A');
        $otherOrganization = $this->createOrganization('PRODUCT-B');
        $entitlements = app(OrganizationEntitlementService::class);
        $entitlements->assignDefaultPlan($storefrontOrganization);
        $entitlements->assignDefaultPlan($otherOrganization);

        $category = Category::query()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Categoria externa',
            'slug' => 'categoria-externa',
        ]);
        $unitMeasure = UnitMeasure::query()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Unidad externa',
        ]);
        $product = ProductFactory::new()->create([
            'organization_id' => $otherOrganization->id,
            'category_id' => $category->id,
            'unit_measure_id' => $unitMeasure->id,
            'name' => 'Producto externo',
            'slug' => 'producto-externo',
            'stock' => 10,
        ]);

        $this->get("/ecommerce/{$storefrontOrganization->slug}/carrito/agregar/{$product->id}")
            ->assertNotFound();
    }

    public function test_suspended_or_unknown_storefront_is_not_exposed(): void
    {
        $organization = $this->createOrganization('SUSPENDED', status: 'suspended');
        app(OrganizationEntitlementService::class)->assignDefaultPlan($organization);

        $this->get('/_test/storefront/suspended')->assertNotFound();
        $this->get('/_test/storefront/does-not-exist')->assertNotFound();
    }

    public function test_storefront_without_ecommerce_capability_is_not_exposed(): void
    {
        $organization = $this->createOrganization('NO-ECOMMERCE');
        $entitlements = app(OrganizationEntitlementService::class);
        $entitlements->assignDefaultPlan($organization);
        $entitlements->setOverride(
            $organization,
            SaasCapability::query()->where('code', 'sales.ecommerce')->firstOrFail(),
            'disabled',
            'Storefront disabled for this tenant.'
        );

        $this->get('/_test/storefront/no-ecommerce')->assertNotFound();
        $this->assertNull(app(StorefrontRouteService::class)->homeForOrganization($organization));
    }

    private function createOrganization(string $code, string $status = 'active'): Organization
    {
        return Organization::query()->create([
            'code' => $code,
            'name' => "Organization {$code}",
            'slug' => str($code)->lower()->replace('_', '-')->toString(),
            'status' => $status,
            'environment' => 'demo',
            'is_default' => false,
            'settings_json' => [],
        ]);
    }
}

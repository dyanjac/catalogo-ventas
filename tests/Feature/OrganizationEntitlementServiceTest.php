<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Entities\SaasCapability;
use Modules\Commerce\Entities\SaasPlan;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Security\Models\SecurityModule;
use Modules\Security\Models\SecurityRole;
use Modules\Security\Services\SecurityAuthorizationService;
use Tests\TestCase;

class OrganizationEntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_plan_keeps_core_inventory_and_denies_advanced_inventory_and_accounting(): void
    {
        $organization = $this->createOrganization('BASIC');
        $service = app(OrganizationEntitlementService::class);

        $service->assignDefaultPlan($organization);

        $this->assertTrue($service->hasCapability('inventory.core.stock', $organization));
        $this->assertTrue($service->hasCapability('sales.orders', $organization));
        $this->assertFalse($service->hasCapability('inventory.advanced', $organization));
        $this->assertFalse($service->hasCapability('accounting.general_ledger', $organization));
    }

    public function test_addon_activation_and_manual_override_are_scoped_to_the_organization(): void
    {
        $organizationA = $this->createOrganization('TENANTA');
        $organizationB = $this->createOrganization('TENANTB');
        $service = app(OrganizationEntitlementService::class);
        $service->assignDefaultPlan($organizationA);
        $service->assignDefaultPlan($organizationB);

        $inventoryAddon = SaasPlan::query()->where('code', 'inventory_advanced')->firstOrFail();
        $capability = SaasCapability::query()->where('code', 'inventory.advanced')->firstOrFail();

        $service->activateAddon($organizationA, $inventoryAddon, ['source' => 'test']);

        $this->assertTrue($service->hasCapability('inventory.advanced', $organizationA));
        $this->assertFalse($service->hasCapability('inventory.advanced', $organizationB));

        $service->setOverride($organizationA, $capability, 'disabled', 'Prueba de baja comercial.');

        $this->assertFalse($service->hasCapability('inventory.advanced', $organizationA));
        $this->assertDatabaseHas('organization_entitlements', [
            'organization_id' => $organizationA->id,
            'capability_id' => $capability->id,
            'state' => 'disabled',
        ]);
    }

    public function test_module_access_requires_both_role_access_and_tenant_entitlement(): void
    {
        $organization = $this->createOrganization('AUTHORG');
        $service = app(OrganizationEntitlementService::class);
        $service->assignDefaultPlan($organization);

        $module = SecurityModule::query()->create([
            'code' => 'inventory',
            'name' => 'Inventarios',
            'status' => 'implemented',
            'navigation_visible' => true,
            'sort_order' => 1,
        ]);
        $role = SecurityRole::query()->create([
            'code' => 'warehouse_manager',
            'name' => 'Responsable de inventario',
            'is_system' => true,
            'is_active' => true,
        ]);
        $role->modules()->attach($module->id, ['access_level' => 'full', 'navigation_visible' => true]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'warehouse_manager',
            'domain' => 'internal',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, ['scope' => 'all', 'is_active' => true, 'context' => json_encode([])]);
        $user = $user->fresh(['organization']);

        $authorization = app(SecurityAuthorizationService::class);

        $this->assertFalse($authorization->canAccessModule($user, 'inventory'));

        $service->activateAddon($organization, SaasPlan::query()->where('code', 'inventory_advanced')->firstOrFail());
        $authorization = app()->make(SecurityAuthorizationService::class);

        $this->assertTrue($authorization->canAccessModule($user, 'inventory'));
    }

    public function test_ecommerce_checkout_is_blocked_when_the_capability_is_disabled(): void
    {
        $organization = $this->createOrganization('CHECKOUT');
        $service = app(OrganizationEntitlementService::class);
        $service->assignDefaultPlan($organization);
        $service->setOverride(
            $organization,
            SaasCapability::query()->where('code', 'sales.ecommerce')->firstOrFail(),
            'disabled',
            'Prueba de baja comercial.'
        );

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'customer',
            'domain' => 'customer',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('checkout.show'))
            ->assertForbidden();
    }

    private function createOrganization(string $code): Organization
    {
        return Organization::query()->create([
            'code' => $code,
            'name' => 'Organización '.$code,
            'slug' => strtolower($code),
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => false,
            'settings_json' => [],
        ]);
    }
}

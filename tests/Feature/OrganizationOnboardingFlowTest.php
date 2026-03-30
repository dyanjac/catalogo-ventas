<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\SecurityRole;
use Tests\TestCase;

class OrganizationOnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    private SecurityRole $superAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = SecurityRole::query()->create([
            'code' => 'super_admin',
            'name' => 'Super administrador',
            'description' => 'Super administrador',
            'is_system' => true,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_complete_demo_to_production_and_suspend_reactivate_flow(): void
    {
        $actor = $this->createSuperAdminActor();

        $response = $this->actingAs($actor)->post(route('admin.organizations.store'), [
            'organization_name' => 'Acme Demo SAC',
            'organization_code' => 'ACMEDEMO',
            'organization_slug' => 'acme-demo',
            'tax_id' => '20123456789',
            'contact_email' => 'contacto@acme.test',
            'phone' => '999999999',
            'address' => 'Av. Demo 123',
            'city' => 'Lima',
            'branch_name' => 'Sucursal Principal',
            'admin_name' => 'Admin Acme',
            'admin_email' => 'admin@acme.test',
            'admin_phone' => '988888888',
        ]);

        $organization = Organization::query()->where('code', 'ACMEDEMO')->firstOrFail();
        $admin = User::query()->where('organization_id', $organization->id)->where('email', 'admin@acme.test')->firstOrFail();

        $response->assertRedirect(route('admin.organizations.show', $organization));
        $response->assertSessionHas('provisioned_credentials');

        $this->assertSame('demo', $organization->environment);
        $this->assertSame('active', $organization->status);
        $this->assertNotNull(data_get($organization->settings_json, 'provisioned_at'));
        $this->assertTrue($admin->isSuperAdmin());

        $this->assertDatabaseHas('security_branches', [
            'organization_id' => $organization->id,
            'name' => 'Sucursal Principal',
            'is_default' => 1,
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('commerce_settings', [
            'organization_id' => $organization->id,
            'email' => 'contacto@acme.test',
        ]);

        $this->assertDatabaseHas('billing_settings', [
            'organization_id' => $organization->id,
            'provider' => 'sunat_greenter',
        ]);

        $this->assertDatabaseHas('accounting_settings', [
            'organization_id' => $organization->id,
            'default_currency' => 'PEN',
        ]);

        $productionResponse = $this->actingAs($actor)->put(route('admin.organizations.activate-production', $organization));

        $productionResponse->assertRedirect(route('admin.organizations.show', $organization));

        $organization->refresh();

        $this->assertSame('production', $organization->environment);
        $this->assertSame('active', $organization->status);
        $this->assertNotNull(data_get($organization->settings_json, 'production_activated_at'));

        $this->assertDatabaseHas('security_audit_logs', [
            'event_code' => 'saas.organization.production_activated',
            'result' => 'success',
        ]);

        $suspendResponse = $this->actingAs($actor)->put(route('admin.organizations.suspend', $organization));

        $suspendResponse->assertRedirect(route('admin.organizations.show', $organization));

        $organization->refresh();

        $this->assertSame('suspended', $organization->status);
        $this->assertNotNull(data_get($organization->settings_json, 'suspended_at'));

        $this->assertDatabaseHas('security_audit_logs', [
            'event_code' => 'saas.organization.suspended',
            'result' => 'success',
        ]);

        $reactivateResponse = $this->actingAs($actor)->put(route('admin.organizations.reactivate', $organization));

        $reactivateResponse->assertRedirect(route('admin.organizations.show', $organization));

        $organization->refresh();

        $this->assertSame('active', $organization->status);
        $this->assertNotNull(data_get($organization->settings_json, 'reactivated_at'));

        $this->assertDatabaseHas('security_audit_logs', [
            'event_code' => 'saas.organization.reactivated',
            'result' => 'success',
        ]);
    }

    public function test_super_admin_cannot_suspend_default_organization(): void
    {
        $actor = $this->createSuperAdminActor();

        $organization = Organization::query()->create([
            'code' => 'DEFAULTORG',
            'name' => 'Organizacion Default',
            'slug' => 'organizacion-default',
            'tax_id' => '20999999999',
            'status' => 'active',
            'environment' => 'production',
            'is_default' => true,
            'settings_json' => [],
        ]);

        $response = $this->from(route('admin.organizations.show', $organization))
            ->actingAs($actor)
            ->put(route('admin.organizations.suspend', $organization));

        $response->assertRedirect(route('admin.organizations.show', $organization));
        $response->assertSessionHasErrors('organization');

        $this->assertSame('active', $organization->fresh()->status);
        $this->assertDatabaseMissing('security_audit_logs', [
            'event_code' => 'saas.organization.suspended',
            'context->organization_id' => $organization->id,
        ]);
    }

    private function createSuperAdminActor(): User
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'domain' => 'internal',
            'is_active' => true,
        ]);

        $user->roles()->syncWithoutDetaching([
            $this->superAdminRole->id => [
                'scope' => 'all',
                'is_active' => true,
                'context' => ['source' => 'tests'],
            ],
        ]);

        return $user->fresh();
    }
}

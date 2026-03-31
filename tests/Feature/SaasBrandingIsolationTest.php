<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Commerce\Services\CommerceSettingsService;
use Modules\Security\Models\SecurityAuthSetting;
use Modules\Security\Models\SecurityRole;
use Modules\Security\Services\SecurityAuthSettingsService;
use Tests\TestCase;

class SaasBrandingIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_commerce_branding_is_isolated_per_organization(): void
    {
        Cache::flush();

        [$organizationA, $userA] = $this->createTenantActor('ORG-A', 'tenant-a');
        [$organizationB, $userB] = $this->createTenantActor('ORG-B', 'tenant-b');

        CommerceSetting::query()->create([
            'organization_id' => $organizationA->id,
            'brand_name' => 'Marca A',
            'company_name' => 'Compania A SAC',
            'tagline' => 'Operacion A',
            'tax_id' => '20111111111',
            'phone' => '111111111',
            'mobile' => '911111111',
            'support_phone' => '911111111',
            'email' => 'ventas-a@test.local',
            'support_email' => 'soporte-a@test.local',
        ]);

        CommerceSetting::query()->create([
            'organization_id' => $organizationB->id,
            'brand_name' => 'Marca B',
            'company_name' => 'Compania B SAC',
            'tagline' => 'Operacion B',
            'tax_id' => '20222222222',
            'phone' => '222222222',
            'mobile' => '922222222',
            'support_phone' => '922222222',
            'email' => 'ventas-b@test.local',
            'support_email' => 'soporte-b@test.local',
        ]);

        $this->actingAs($userA);
        $commerceA = app(CommerceSettingsService::class)->getForView();
        auth()->logout();

        $this->actingAs($userB);
        $commerceB = app(CommerceSettingsService::class)->getForView();

        $this->assertSame('Marca A', $commerceA['brand_name']);
        $this->assertSame('Compania A SAC', $commerceA['legal_name']);
        $this->assertSame('Operacion A', $commerceA['tagline']);
        $this->assertSame('soporte-a@test.local', $commerceA['support_email']);
        $this->assertSame('Marca B', $commerceB['brand_name']);
        $this->assertSame('Compania B SAC', $commerceB['legal_name']);
        $this->assertSame('Operacion B', $commerceB['tagline']);
        $this->assertSame('soporte-b@test.local', $commerceB['support_email']);
    }

    public function test_security_auth_branding_is_isolated_per_organization(): void
    {
        [$organizationA, $userA] = $this->createTenantActor('ORG-C', 'tenant-c');
        [$organizationB, $userB] = $this->createTenantActor('ORG-D', 'tenant-d');

        SecurityAuthSetting::query()->create([
            'organization_id' => $organizationA->id,
            'auth_method' => 'internal',
            'login_headline' => 'Ingreso Marca C',
            'login_slogan' => 'Slogan C',
            'oauth_google_enabled' => true,
            'ldap_enabled' => false,
        ]);

        SecurityAuthSetting::query()->create([
            'organization_id' => $organizationB->id,
            'auth_method' => 'ldap',
            'login_headline' => 'Ingreso Marca D',
            'login_slogan' => 'Slogan D',
            'oauth_google_enabled' => false,
            'ldap_enabled' => true,
        ]);

        $this->actingAs($userA);
        $authA = app(SecurityAuthSettingsService::class)->getForView();
        auth()->logout();

        $this->actingAs($userB);
        $authB = app(SecurityAuthSettingsService::class)->getForView();

        $this->assertSame('Ingreso Marca C', $authA['login_headline']);
        $this->assertSame('Slogan C', $authA['login_slogan']);
        $this->assertTrue($authA['oauth_google_enabled']);
        $this->assertFalse($authA['ldap_enabled']);

        $this->assertSame('Ingreso Marca D', $authB['login_headline']);
        $this->assertSame('Slogan D', $authB['login_slogan']);
        $this->assertFalse($authB['oauth_google_enabled']);
        $this->assertTrue($authB['ldap_enabled']);
    }

    public function test_onboarding_seeds_initial_branding_and_login_copy(): void
    {
        SecurityRole::query()->create([
            'code' => 'super_admin',
            'name' => 'Super administrador',
            'description' => 'Super administrador',
            'is_system' => true,
            'is_active' => true,
        ]);

        $result = app(OrganizationProvisioningService::class)->provisionDemoOrganization([
            'organization_name' => 'Acme Tenant SAC',
            'organization_code' => 'ACME',
            'organization_slug' => 'acme-tenant',
            'brand_name' => 'Acme Brand',
            'tagline' => 'Gestion comercial unificada',
            'tax_id' => '20444444444',
            'contact_email' => 'ventas@acme.test',
            'support_email' => 'soporte@acme.test',
            'phone' => '955555555',
            'support_phone' => '966666666',
            'address' => 'Av. Tenant 123',
            'city' => 'Lima',
            'branch_name' => 'Sucursal Principal',
            'admin_name' => 'Admin Acme',
            'admin_email' => 'admin@acme.test',
            'admin_phone' => '977777777',
            'provisioned_via' => 'tests',
        ]);

        $organization = $result['organization'];

        $this->assertDatabaseHas('commerce_settings', [
            'organization_id' => $organization->id,
            'brand_name' => 'Acme Brand',
            'company_name' => 'Acme Tenant SAC',
            'tagline' => 'Gestion comercial unificada',
            'support_email' => 'soporte@acme.test',
            'support_phone' => '966666666',
        ]);

        $this->assertDatabaseHas('security_auth_settings', [
            'organization_id' => $organization->id,
            'login_slogan' => 'Gestion comercial unificada',
        ]);
    }

    /**
     * @return array{0:Organization,1:User}
     */
    private function createTenantActor(string $code, string $slug): array
    {
        $organization = Organization::query()->create([
            'code' => $code,
            'name' => 'Tenant '.$code,
            'slug' => $slug,
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => false,
            'settings_json' => [],
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'super_admin',
            'is_active' => true,
            'domain' => 'internal',
        ]);

        return [$organization, $user];
    }
}

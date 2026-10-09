<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContextService;
use App\Services\SharedNavigationCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Security\Models\SecurityModule;
use Modules\Security\Models\SecurityRole;
use Modules\Security\Services\SecurityAuthorizationService;
use Tests\TestCase;

class NavigationResponseMemoizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.navigation.enabled' => true,
            'cache.navigation.store' => 'array',
            'cache.navigation.ttl_seconds' => 300,
        ]);
        Cache::store('array')->flush();
    }

    public function test_organization_context_is_resolved_once_per_request_lifecycle(): void
    {
        $organization = $this->createOrganization('CONTEXT');
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $this->actingAs($user);

        $queries = 0;
        $listening = false;
        DB::listen(function () use (&$queries, &$listening): void {
            if ($listening) {
                $queries++;
            }
        });

        $context = app(OrganizationContextService::class);
        $listening = true;
        $first = $context->forView();
        $queriesAfterFirstResolution = $queries;
        $second = $context->forView();
        $listening = false;

        $this->assertSame($first, $second);
        $this->assertSame($organization->id, $first['organization_id']);
        $this->assertLessThanOrEqual(2, $queriesAfterFirstResolution);
        $this->assertSame($queriesAfterFirstResolution, $queries);
        $this->assertSame($context, app(OrganizationContextService::class));
    }

    public function test_navigation_is_memoized_within_query_budget(): void
    {
        $organization = $this->createOrganization('NAVIGATION');
        app(OrganizationEntitlementService::class)->assignDefaultPlan($organization);

        foreach ([
            ['code' => 'dashboard', 'name' => 'Dashboard', 'sort_order' => 10],
            ['code' => 'sales', 'name' => 'Ventas', 'sort_order' => 20],
        ] as $moduleData) {
            SecurityModule::query()->updateOrCreate(
                ['code' => $moduleData['code']],
                $moduleData + [
                    'status' => 'implemented',
                    'navigation_visible' => true,
                ]
            );
        }

        $role = SecurityRole::query()->firstOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super administrador', 'is_active' => true]
        );
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, [
            'scope' => 'all',
            'is_active' => true,
            'context' => null,
        ]);
        $user->load('organization');

        $queries = 0;
        $listening = false;
        DB::listen(function () use (&$queries, &$listening): void {
            if ($listening) {
                $queries++;
            }
        });

        $authorization = app(SecurityAuthorizationService::class);
        $listening = true;
        $first = $authorization->modulesForNavigation($user);
        $queriesAfterFirstResolution = $queries;
        $second = $authorization->modulesForNavigation($user);
        $listening = false;

        $this->assertSame($first->pluck('id')->all(), $second->pluck('id')->all());
        $this->assertTrue($first->contains('code', 'dashboard'));
        $this->assertTrue($first->contains('code', 'sales'));
        $this->assertLessThanOrEqual(12, $queriesAfterFirstResolution);
        $this->assertSame($queriesAfterFirstResolution, $queries);
        $this->assertSame($authorization, app(SecurityAuthorizationService::class));
    }

    public function test_authorization_cache_can_be_invalidated_after_role_changes(): void
    {
        $organization = $this->createOrganization('ROLECACHE');
        $role = SecurityRole::query()->firstOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super administrador', 'is_active' => true]
        );
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'customer',
            'is_active' => true,
        ]);
        $authorization = app(SecurityAuthorizationService::class);

        $this->assertFalse($authorization->hasRole($user, 'super_admin'));

        $user->roles()->attach($role->id, [
            'scope' => 'all',
            'is_active' => true,
            'context' => null,
        ]);
        $authorization->forgetUser($user);

        $this->assertTrue($authorization->hasRole($user, 'super_admin'));
    }

    public function test_shared_navigation_cache_is_reused_and_invalidated_across_service_instances(): void
    {
        $organization = $this->createOrganization('SHAREDNAV');
        app(OrganizationEntitlementService::class)->assignDefaultPlan($organization);

        SecurityModule::query()->create([
            'code' => 'dashboard',
            'name' => 'Dashboard',
            'status' => 'implemented',
            'navigation_visible' => true,
            'sort_order' => 10,
        ]);
        $role = SecurityRole::query()->create([
            'code' => 'super_admin',
            'name' => 'Super administrador',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, [
            'scope' => 'all',
            'is_active' => true,
            'context' => null,
        ]);
        $user->load('organization');

        $firstAuthorization = $this->newAuthorizationService();
        $this->assertTrue($firstAuthorization->modulesForNavigation($user)->contains('code', 'dashboard'));

        $queries = 0;
        $listening = false;
        DB::listen(function () use (&$queries, &$listening): void {
            if ($listening) {
                $queries++;
            }
        });

        $listening = true;
        $cachedModules = $this->newAuthorizationService()->modulesForNavigation($user);
        $listening = false;

        $this->assertSame(0, $queries);
        $this->assertTrue($cachedModules->contains('code', 'dashboard'));

        SecurityModule::query()->create([
            'code' => 'operations',
            'name' => 'Operaciones',
            'status' => 'implemented',
            'navigation_visible' => true,
            'sort_order' => 20,
        ]);
        $firstAuthorization->forgetUser($user);

        $this->assertTrue(
            $this->newAuthorizationService()->modulesForNavigation($user)->contains('code', 'operations')
        );
    }

    private function newAuthorizationService(): SecurityAuthorizationService
    {
        $sharedCache = app(SharedNavigationCache::class);
        $entitlements = new OrganizationEntitlementService(
            app(OrganizationContextService::class),
            $sharedCache
        );

        return new SecurityAuthorizationService($entitlements, $sharedCache);
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

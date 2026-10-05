<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\SecurityRole;
use Tests\TestCase;

class CoreEnsureSuperAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_super_admin_can_access_admin_theme_route(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('admin.theme.edit'))
            ->assertOk();
    }

    public function test_customer_is_forbidden_on_admin_theme_route(): void
    {
        $customer = $this->userWithRole('customer');

        $this->actingAs($customer)
            ->get(route('admin.theme.edit'))
            ->assertForbidden();
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create([
            'role' => $roleCode,
            'is_active' => true,
        ]);
        $role = SecurityRole::query()->where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'scope' => 'all',
            'is_active' => true,
            'context' => null,
        ]);

        return $user;
    }

    private function createRoles(): void
    {
        SecurityRole::query()->create([
            'code' => 'super_admin',
            'name' => 'Super administrador',
            'is_active' => true,
        ]);
        SecurityRole::query()->create([
            'code' => 'customer',
            'name' => 'Cliente',
            'is_active' => true,
        ]);
    }
}

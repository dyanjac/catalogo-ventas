<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Security\Models\SecurityRole;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
    }

    public function test_guest_is_redirected_when_accessing_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $customer = $this->userWithRole('customer');

        $response = $this->actingAs($customer)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_super_admin_can_access_admin_dashboard(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $response = $this
            ->withHeader('X-Livewire-Navigate', '1')
            ->actingAs($superAdmin)
            ->get(route('admin.dashboard'));

        $response
            ->assertOk()
            ->assertSee('wire:navigate', false)
            ->assertDontSee('wire:navigate.hover', false);
    }

    public function test_customer_cannot_access_admin_orders_index(): void
    {
        $customer = $this->userWithRole('customer');

        $response = $this->actingAs($customer)->get(route('admin.orders.index'));

        $response->assertForbidden();
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

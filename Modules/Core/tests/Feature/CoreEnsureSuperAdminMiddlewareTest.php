<?php

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreEnsureSuperAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_admin_theme_route(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.theme.edit'))
            ->assertOk();
    }

    public function test_customer_is_forbidden_on_admin_theme_route(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->get(route('admin.theme.edit'))
            ->assertForbidden();
    }
}

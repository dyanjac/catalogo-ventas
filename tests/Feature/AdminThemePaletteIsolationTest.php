<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AdminTheme\Models\AdminThemeSetting;
use Modules\AdminTheme\Services\AdminThemePaletteService;
use Tests\TestCase;

class AdminThemePaletteIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_tenant_cannot_update_or_reset_admin_palette(): void
    {
        $organization = Organization::query()->create([
            'code' => 'SUSP',
            'name' => 'Tenant Suspendido',
            'slug' => 'tenant-suspendido',
            'status' => 'suspended',
            'environment' => 'demo',
            'is_default' => false,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        AdminThemeSetting::query()->create([
            'organization_id' => $organization->id,
            'sidebar_bg' => '#111111',
            'sidebar_gradient_to' => '#222222',
            'sidebar_text' => '#FFFFFF',
            'sidebar_group_text' => '#FFFFFF',
            'sidebar_group_bg' => '#333333',
            'topbar_bg' => '#FFFFFF',
            'topbar_text' => '#111111',
            'primary_button' => '#444444',
            'primary_button_hover' => '#555555',
            'active_link_bg' => '#666666',
            'active_link_text' => '#FFFFFF',
            'card_border' => '#777777',
            'focus_ring' => '#888888',
        ]);

        $payload = [
            'sidebar_bg' => '#AAAAAA',
            'sidebar_gradient_to' => '#BBBBBB',
            'sidebar_text' => '#FFFFFF',
            'sidebar_group_text' => '#FFFFFF',
            'sidebar_group_bg' => '#CCCCCC',
            'topbar_bg' => '#FFFFFF',
            'topbar_text' => '#111111',
            'primary_button' => '#DDDDDD',
            'primary_button_hover' => '#EEEEEE',
            'active_link_bg' => '#999999',
            'active_link_text' => '#FFFFFF',
            'card_border' => '#121212',
            'focus_ring' => '#131313',
        ];

        $this->actingAs($user)
            ->put(route('admin.theme.update'), $payload)
            ->assertRedirect(route('admin.theme.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('admin_theme_settings', [
            'organization_id' => $organization->id,
            'primary_button' => '#444444',
        ]);

        $this->actingAs($user)
            ->delete(route('admin.theme.reset'))
            ->assertRedirect(route('admin.theme.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('admin_theme_settings', [
            'organization_id' => $organization->id,
            'primary_button' => '#444444',
        ]);
    }

    public function test_palette_service_is_isolated_per_organization(): void
    {
        $organizationA = Organization::query()->create([
            'code' => 'ORG-A',
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => false,
        ]);

        $organizationB = Organization::query()->create([
            'code' => 'ORG-B',
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => false,
        ]);

        $userA = User::factory()->create([
            'organization_id' => $organizationA->id,
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $userB = User::factory()->create([
            'organization_id' => $organizationB->id,
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        AdminThemeSetting::query()->create(array_merge([
            'organization_id' => $organizationA->id,
        ], config('admintheme.defaults', []), [
            'primary_button' => '#112233',
        ]));

        AdminThemeSetting::query()->create(array_merge([
            'organization_id' => $organizationB->id,
        ], config('admintheme.defaults', []), [
            'primary_button' => '#445566',
        ]));

        $this->actingAs($userA);
        $paletteA = app(AdminThemePaletteService::class)->getPalette();
        auth()->logout();

        $this->actingAs($userB);
        $paletteB = app(AdminThemePaletteService::class)->getPalette();

        $this->assertSame('#112233', $paletteA['primary_button']);
        $this->assertSame('#445566', $paletteB['primary_button']);
    }
}

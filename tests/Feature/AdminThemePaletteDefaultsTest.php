<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\AdminTheme\Models\AdminThemeSetting;
use Modules\AdminTheme\Services\AdminThemePaletteService;
use Tests\TestCase;

class AdminThemePaletteDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function organization(string $code): Organization
    {
        return Organization::query()->create([
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => false,
        ]);
    }

    public function test_defaults_can_be_saved_and_reset_without_validation_errors(): void
    {
        $organization = $this->organization('PALETTE');
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $role = \Modules\Security\Models\SecurityRole::query()->firstOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super Admin', 'is_active' => true, 'is_system' => true]
        );
        $user->roles()->attach($role->id, ['scope' => 'all', 'is_active' => true]);
        $this->actingAs($user);
        $service = app(AdminThemePaletteService::class);
        $defaults = config('admintheme.defaults');
        $this->assertSame($defaults, $service->getPalette());
        $this->put(route('admin.theme.update'), $defaults)
            ->assertRedirect(route('admin.theme.edit'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('admin_theme_settings', array_merge(['organization_id' => $organization->id], $defaults));

        $payload = array_merge($defaults, ['primary_button' => '#123456', 'card_border' => '#1234562e', 'focus_ring' => '#12345640']);
        $this->put(route('admin.theme.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('#123456', $service->getPalette()['primary_button']);
        $this->assertSame('#12345640', $service->getPalette()['focus_ring']);
        $this->assertSame('#1234562E', $service->getPalette()['card_border']);
        $this->get(route('admin.theme.edit'))->assertOk()->assertSee('value="#12345640"', false)->assertSee('value="#123456"', false);
        $this->put(route('admin.theme.update'), array_merge($defaults, ['primary_button' => '#12345640']))
            ->assertSessionHasErrors('primary_button');
        $this->delete(route('admin.theme.reset'))->assertRedirect(route('admin.theme.edit'));
        $this->assertDatabaseMissing('admin_theme_settings', ['organization_id' => $organization->id]);
        $this->assertSame($defaults, $service->getPalette());
    }

    public function test_default_seeder_preserves_an_existing_custom_palette(): void
    {
        $this->seed(\Database\Seeders\DefaultOrganizationSeeder::class);
        $organization = Organization::query()->where('code', 'DEFAULT')->firstOrFail();
        $setting = AdminThemeSetting::query()->where('organization_id', $organization->id)->firstOrFail();
        $setting->update(['primary_button' => '#112233']);
        $before = $setting->fresh()->getAttributes();
        $this->seed(\Database\Seeders\DefaultOrganizationSeeder::class);
        $this->assertSame($before, $setting->fresh()->getAttributes());
    }

    public function test_migration_updates_only_the_effective_historical_palette(): void
    {
        $migration = require base_path('Modules/AdminTheme/database/migrations/2026_10_05_120000_refresh_default_admin_palette.php');
        $reflection = new \ReflectionClass($migration);
        $old = $reflection->getConstant('OLD_PALETTE');
        $new = $reflection->getConstant('NEW_PALETTE');
        $this->assertSame(config('admintheme.defaults'), $new);
        $original = $this->organization('ORIGINAL');
        $legacy = $this->organization('LEGACY');
        $custom = $this->organization('CUSTOM');
        $originalSetting = AdminThemeSetting::query()->create(array_merge(['organization_id' => $original->id], $old));
        $legacyPalette = array_map('strtoupper', $old);
        foreach (array_keys($legacyPalette) as $key) {
            if (str_starts_with($key, 'user_menu_')) {
                $legacyPalette[$key] = null;
            }
        }
        AdminThemeSetting::query()->create(array_merge(['organization_id' => $legacy->id], $legacyPalette));
        $customPalette = array_merge($old, ['primary_button' => '#112233']);
        $customSetting = AdminThemeSetting::query()->create(array_merge(['organization_id' => $custom->id], $customPalette));
        $before = $customSetting->fresh()->getAttributes();
        Cache::forever('admin_theme_palette_v1:'.$original->id, $old);
        Cache::forever('admin_theme_palette_v2:'.$original->id, $old);
        $migration->up();
        $migration->up();
        foreach ([$original, $legacy] as $organization) {
            $this->assertDatabaseHas('admin_theme_settings', array_merge(['organization_id' => $organization->id], $new));
        }
        $this->assertSame($before, $customSetting->fresh()->getAttributes());
        $this->assertNull(Cache::get('admin_theme_palette_v1:'.$original->id));
        $this->assertNull(Cache::get('admin_theme_palette_v2:'.$original->id));
        $originalSetting->update(['primary_button' => '#445566']);
        $migration->down();
        $this->assertSame('#445566', $originalSetting->fresh()->primary_button);
    }
}

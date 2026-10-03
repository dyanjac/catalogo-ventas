<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Security\Livewire\AdminLoginScreen;
use Modules\Security\Models\SecurityAuthSetting;
use Modules\Security\Models\SecurityRole;
use Modules\Security\Services\LdapDirectoryService;
use Tests\TestCase;

class AdminLoginExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Livewire component tests disable web middleware; this flow needs its session.
        $this->app->rebinding('request', function ($app, $request): void {
            $request->setLaravelSession($app['session']->driver());
        });
    }

    public function test_entry_page_is_corporate_until_an_organization_is_identified(): void
    {
        $organization = $this->organization('alpha');
        CommerceSetting::query()->create([
            'organization_id' => $organization->id,
            'brand_name' => 'Marca privada Alpha',
            'company_name' => 'Alpha SAC',
            'email' => 'contacto@alpha.test',
        ]);

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSeeLivewire(AdminLoginScreen::class)
            ->assertSee(config('marketing.brand_name'))
            ->assertDontSee('Marca privada Alpha');

        Livewire::test(AdminLoginScreen::class)
            ->call('identifyOrganization')
            ->assertHasErrors('identifier')
            ->assertHasNoErrors('password');
    }

    public function test_identification_shows_the_selected_brand_and_clearing_it_removes_password(): void
    {
        $organization = $this->organization('alpha');
        User::factory()->create(['organization_id' => $organization->id, 'email' => 'admin@alpha.test', 'is_active' => true]);
        CommerceSetting::query()->create([
            'organization_id' => $organization->id,
            'brand_name' => 'Marca Alpha',
            'company_name' => 'Alpha SAC',
            'email' => 'contacto@alpha.test',
            'support_email' => 'soporte@alpha.test',
        ]);

        Livewire::test(AdminLoginScreen::class)
            ->set('identifier', 'admin@alpha.test')
            ->call('identifyOrganization')
            ->assertSet('selectedOrganizationSlug', 'alpha')
            ->assertSee('Marca Alpha')
            ->assertSee('soporte@alpha.test')
            ->set('password', 'temporary-input')
            ->call('clearOrganizationSelection')
            ->assertSet('selectedOrganizationSlug', '')
            ->assertSet('password', '')
            ->assertDontSee('Marca Alpha')
            ->assertDontSee('soporte@alpha.test');
    }

    public function test_shared_email_authenticates_only_against_selected_organization_without_foreign_ldap_settings(): void
    {
        config(['security.auth.ldap_enabled' => false]);
        $alpha = $this->organization('alpha');
        $beta = $this->organization('beta');
        SecurityAuthSetting::query()->create([
            'organization_id' => $alpha->id,
            'ldap_enabled' => true,
            'login_headline' => 'Exclusivo Alpha',
        ]);
        $this->mock(LdapDirectoryService::class)->shouldNotReceive('authenticate');
        User::factory()->create([
            'organization_id' => $alpha->id, 'email' => 'shared@example.test',
            'password' => 'alpha-password', 'is_active' => true,
        ]);
        $betaUser = User::factory()->create([
            'organization_id' => $beta->id, 'email' => 'shared@example.test',
            'password' => 'beta-password', 'is_active' => true,
        ]);
        $role = SecurityRole::query()->create(['code' => 'super_admin', 'name' => 'Admin', 'is_active' => true, 'is_system' => true]);
        $betaUser->roles()->attach($role->id, ['scope' => 'all', 'is_active' => true]);

        $component = Livewire::test(AdminLoginScreen::class)
            ->set('identifier', 'shared@example.test')
            ->call('identifyOrganization')
            ->assertSet('selectedOrganizationSlug', '')
            ->assertCount('organizationOptions', 2)
            ->call('selectOrganization', 'beta')
            ->assertDontSee('Exclusivo Alpha')
            ->set('password', 'alpha-password')
            ->call('login')
            ->assertHasErrors('identifier');

        $this->assertGuest();
        $component->set('password', 'beta-password')
            ->call('login')
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($betaUser);
    }

    public function test_direct_link_resolves_brand_and_suspended_organization_cannot_log_in(): void
    {
        $organization = $this->organization('suspended');
        $organization->update(['status' => 'suspended']);
        User::factory()->create([
            'organization_id' => $organization->id, 'email' => 'admin@suspended.test',
            'password' => 'valid-password', 'is_active' => true,
        ]);

        $this->get(route('admin.login', ['org' => 'suspended']))->assertOk()->assertSee('Organization suspended');
        Livewire::withQueryParams(['org' => 'suspended'])->test(AdminLoginScreen::class)
            ->assertSet('selectedOrganizationSlug', 'suspended')
            ->set('identifier', 'admin@suspended.test')
            ->set('password', 'valid-password')
            ->call('login')
            ->assertHasErrors('identifier');
        $this->assertGuest();
    }

    private function organization(string $slug): Organization
    {
        return Organization::query()->create([
            'code' => strtoupper($slug), 'slug' => $slug, 'name' => 'Organization '.$slug,
            'status' => 'active', 'environment' => 'demo', 'is_default' => false, 'settings_json' => [],
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Commerce\Services\CommerceSettingsService;
use Modules\Security\Models\SecurityRole;
use Tests\TestCase;

class SaasRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_load_tenant_commerce_branding(): void
    {
        $this->mock(CommerceSettingsService::class)->shouldNotReceive('getForView');

        $this->withSession(['organization_context_slug' => 'another-company'])
            ->get(route('saas.register.create'))
            ->assertOk()
            ->assertSee(config('marketing.brand_name'))
            ->assertDontSee('class="auth-shell"', false);
    }

    public function test_invalid_optional_email_preserves_inputs_and_exposes_its_error(): void
    {
        $payload = $this->validPayload() + ['support_email' => 'invalid-email'];

        $this->from(route('saas.register.create'))
            ->post(route('saas.register.store'), $payload)
            ->assertRedirect(route('saas.register.create'))
            ->assertSessionHasErrors('support_email');

        $response = $this->get(route('saas.register.create'))
            ->assertOk()
            ->assertSee('value="Acme Demo"', false)
            ->assertSee('value="invalid-email"', false)
            ->assertSee('id="registration-support_email-error"', false)
            ->assertSee('Ingresa un correo válido en correo de soporte.');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//input[@name="support_email"]/ancestor::details[@open]')->length);
        $this->assertDatabaseMissing('organizations', ['code' => 'ACMEDEMO']);
    }

    public function test_public_registration_creates_demo_and_shows_credentials_with_tenant_login(): void
    {
        SecurityRole::query()->create([
            'code' => 'super_admin',
            'name' => 'Super administrador',
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->post(route('saas.register.store'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('saas.register.create'))
            ->assertSessionHas('provisioned_credentials');

        $organization = Organization::query()->where('code', 'ACMEDEMO')->firstOrFail();
        $admin = User::query()->where('organization_id', $organization->id)->firstOrFail();
        $credentials = session('provisioned_credentials');

        $this->assertSame('demo', $organization->environment);
        $this->assertSame('admin@acme.test', $admin->email);
        $this->assertTrue(Hash::check($credentials['generated_password'], $admin->password));
        $this->assertSame(route('admin.login', ['org' => 'acme-demo']), $credentials['admin_login_url']);

        $this->get(route('saas.register.create'))
            ->assertOk()
            ->assertSee('Tu espacio DEMO está listo.')
            ->assertSee($credentials['generated_password'])
            ->assertSee('href="'.$credentials['admin_login_url'].'"', false)
            ->assertDontSee('action="'.route('saas.register.store').'"', false);
    }

    private function validPayload(): array
    {
        return [
            'organization_name' => 'Acme Demo',
            'organization_code' => 'ACMEDEMO',
            'organization_slug' => 'acme-demo',
            'contact_email' => 'contacto@acme.test',
            'branch_name' => 'Sucursal Principal',
            'admin_name' => 'Admin Acme',
            'admin_email' => 'admin@acme.test',
        ];
    }
}

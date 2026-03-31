<?php

namespace Modules\Security\Livewire;

use App\Services\OrganizationContextService;
use Livewire\Component;
use Modules\Security\Services\SecurityAuditService;
use Modules\Security\Services\SecurityAuthorizationService;
use Modules\Security\Services\SecurityAuthSettingsService;

class AuthenticationSettingsScreen extends Component
{
    public array $form = [];

    public string $ldapTestIdentifier = '';

    public string $ldapTestPassword = '';

    public ?string $statusMessage = null;

    public string $statusTone = 'success';

    public ?string $organizationName = null;

    public function mount(SecurityAuthSettingsService $settingsService, OrganizationContextService $organizationContext): void
    {
        $this->form = $settingsService->getForView();
        $this->organizationName = $organizationContext->current()?->name;
    }

    public function chooseAuthMethod(string $method): void
    {
        if (in_array($method, ['internal', 'ldap', 'oauth'], true)) {
            $this->form['auth_method'] = $method;
        }
    }

    public function chooseOauthProvider(string $provider): void
    {
        if (in_array($provider, ['google', 'github', 'custom'], true)) {
            $this->form['oauth_provider'] = $provider;
        }
    }

    public function save(SecurityAuthSettingsService $settingsService, SecurityAuditService $audit): void
    {
        abort_unless(
            app(SecurityAuthorizationService::class)->hasPermission(auth()->user(), 'security.auth.configure'),
            403
        );

        $validated = $this->validate([
            'form.session_lifetime_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'form.auth_method' => ['required', 'in:internal,ldap,oauth'],
            'form.password_min_length' => ['required', 'integer', 'min:8', 'max:64'],
            'form.sso_enabled' => ['boolean'],
            'form.hide_internal_prompt' => ['boolean'],
            'form.auto_user_provisioning' => ['boolean'],
            'form.oauth_auto_team_membership' => ['boolean'],
            'form.oauth_provider' => ['required', 'in:google,github,custom'],
            'form.oauth_google_enabled' => ['boolean'],
            'form.oauth_github_enabled' => ['boolean'],
            'form.oauth_custom_enabled' => ['boolean'],
            'form.oauth_client_id' => ['nullable', 'string', 'max:255'],
            'form.oauth_client_secret' => ['nullable', 'string', 'max:1000'],
            'form.oauth_authorization_url' => ['nullable', 'url', 'max:255'],
            'form.oauth_token_url' => ['nullable', 'url', 'max:255'],
            'form.oauth_resource_url' => ['nullable', 'url', 'max:255'],
            'form.oauth_redirect_url' => ['nullable', 'url', 'max:255'],
            'form.oauth_logout_url' => ['nullable', 'url', 'max:255'],
            'form.oauth_user_identifier' => ['nullable', 'string', 'max:120'],
            'form.oauth_scopes' => ['nullable', 'string', 'max:255'],
            'form.oauth_auth_style' => ['required', 'in:auto,basic,request_body'],
            'form.ldap_enabled' => ['boolean'],
            'form.ldap_host' => ['nullable', 'string', 'max:255'],
            'form.ldap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'form.ldap_anonymous' => ['boolean'],
            'form.ldap_bind_dn' => ['nullable', 'string', 'max:255'],
            'form.ldap_bind_password' => ['nullable', 'string', 'max:1000'],
            'form.ldap_use_starttls' => ['boolean'],
            'form.ldap_use_tls' => ['boolean'],
            'form.ldap_verify_certificate' => ['boolean'],
            'form.ldap_base_dn' => ['nullable', 'string', 'max:255'],
            'form.ldap_user_filter' => ['nullable', 'string', 'max:255'],
            'form.ldap_username_attribute' => ['nullable', 'string', 'max:120'],
            'form.ldap_email_attribute' => ['nullable', 'string', 'max:120'],
            'form.ldap_group_base_dn' => ['nullable', 'string', 'max:255'],
            'form.ldap_group_filter' => ['nullable', 'string', 'max:255'],
            'form.ldap_group_membership_attribute' => ['nullable', 'string', 'max:120'],
            'form.ldap_assign_admin_by_group' => ['boolean'],
            'form.ldap_admin_group_names' => ['nullable', 'string', 'max:500'],
            'form.ldap_group_role_map' => ['nullable', 'string', 'max:5000'],
            'form.ldap_fallback_email_domain' => ['nullable', 'string', 'max:160'],
            'form.login_headline' => ['nullable', 'string', 'max:120'],
            'form.login_slogan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->form = $settingsService->update($validated['form']);
        $this->statusTone = 'success';
        $this->statusMessage = 'Configuracion de autenticacion actualizada correctamente para la organizacion actual.';
        $this->dispatch('security-auth-settings-updated');

        $audit->log(
            eventType: 'configuration',
            eventCode: 'security.auth.settings.updated',
            result: 'success',
            message: 'Se actualizo la configuracion de autenticacion del panel.',
            actor: auth()->user(),
            context: [
                'organization_name' => $this->organizationName,
                'auth_method' => $this->form['auth_method'] ?? 'internal',
                'ldap_enabled' => (bool) ($this->form['ldap_enabled'] ?? false),
                'oauth_provider' => $this->form['oauth_provider'] ?? null,
            ],
        );
    }

    public function testLdap(SecurityAuditService $audit): void
    {
        abort_unless(
            app(SecurityAuthorizationService::class)->hasPermission(auth()->user(), 'security.auth.configure'),
            403
        );

        $validated = $this->validate([
            'form.ldap_enabled' => ['boolean'],
            'form.ldap_host' => ['nullable', 'string', 'max:255'],
            'form.ldap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'form.ldap_anonymous' => ['boolean'],
            'form.ldap_bind_dn' => ['nullable', 'string', 'max:255'],
            'form.ldap_bind_password' => ['nullable', 'string', 'max:1000'],
            'form.ldap_use_starttls' => ['boolean'],
            'form.ldap_use_tls' => ['boolean'],
            'form.ldap_verify_certificate' => ['boolean'],
            'form.ldap_base_dn' => ['nullable', 'string', 'max:255'],
            'form.ldap_user_filter' => ['nullable', 'string', 'max:255'],
            'form.ldap_username_attribute' => ['nullable', 'string', 'max:120'],
            'form.ldap_email_attribute' => ['nullable', 'string', 'max:120'],
            'ldapTestIdentifier' => ['nullable', 'string', 'max:255'],
            'ldapTestPassword' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = app(\Modules\Security\Services\LdapDirectoryService::class)->testConnection(
                $validated['form'],
                trim($validated['ldapTestIdentifier']),
                $validated['ldapTestPassword']
            );

            $this->statusTone = 'success';
            $this->statusMessage = $result['message'];

            $audit->log(
                eventType: 'authentication',
                eventCode: 'security.ldap.test.success',
                result: 'success',
                message: 'La prueba LDAP respondio correctamente.',
                actor: auth()->user(),
                context: [
                    'organization_name' => $this->organizationName,
                    'identifier' => trim($validated['ldapTestIdentifier']),
                    'host' => $validated['form']['ldap_host'] ?? null,
                ],
            );
        } catch (\Throwable $exception) {
            report($exception);
            $this->statusTone = 'danger';
            $this->statusMessage = $exception->getMessage() !== '' ? $exception->getMessage() : 'La prueba LDAP fallo.';

            $audit->log(
                eventType: 'authentication',
                eventCode: 'security.ldap.test.failed',
                result: 'failed',
                message: $this->statusMessage,
                actor: auth()->user(),
                context: [
                    'organization_name' => $this->organizationName,
                    'identifier' => trim($validated['ldapTestIdentifier']),
                    'host' => $validated['form']['ldap_host'] ?? null,
                ],
            );
        }
    }

    public function render()
    {
        return view('security::settings.livewire.authentication-settings-screen');
    }
}

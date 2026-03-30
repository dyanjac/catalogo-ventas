<?php

namespace Modules\Security\Livewire;

use App\Services\OrganizationContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Security\Services\LdapDirectoryService;
use Modules\Security\Services\SecurityAuditService;
use Modules\Security\Services\SecurityAuthorizationService;
use Modules\Security\Services\SecurityAuthSettingsService;
use Throwable;

class AdminLoginScreen extends Component
{
    public string $identifier = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(SecurityAuthorizationService $authorization): void
    {
        if (Auth::check()) {
            $this->redirectAuthenticatedUser($authorization);
        }
    }

    public function login(SecurityAuthorizationService $authorization, SecurityAuditService $audit): void
    {
        $settings = app(SecurityAuthSettingsService::class)->getForView();
        $ldapEnabled = (bool) ($settings['ldap_enabled'] ?? false);
        $organizationContext = app(OrganizationContextService::class);

        $credentials = $this->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $identifier = trim($credentials['identifier']);
        $looksLikeEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;

        if ($organizationContext->isSuspended()) {
            $organization = $organizationContext->current();
            $message = 'La organización actual está suspendida y no permite ingresos al panel administrativo.';
            $this->addError('identifier', $message);
            $audit->log('authentication', 'security.admin.login.suspended_tenant_denied', 'warning', $message, null, null, 'security', [
                'organization_id' => $organization?->id,
                'organization_code' => $organization?->code,
                'identifier' => $identifier,
            ]);

            return;
        }

        if ($looksLikeEmail && $this->attemptInternalLogin($identifier, $credentials['password'], $authorization, $audit, $organizationContext)) {
            return;
        }

        if ($ldapEnabled) {
            try {
                $user = app(LdapDirectoryService::class)->authenticate(
                    $identifier,
                    $credentials['password'],
                    $settings
                );
            } catch (Throwable $exception) {
                report($exception);

                $message = $looksLikeEmail
                    ? 'Credenciales internas invalidas y LDAP respondio: '.$exception->getMessage()
                    : ($exception->getMessage() !== '' ? $exception->getMessage() : 'No se pudo autenticar contra LDAP.');

                $this->addError('identifier', $message);
                $audit->log('authentication', 'security.admin.login.ldap.failed', 'failed', $message, null, null, 'security', ['identifier' => $identifier]);

                return;
            }

            if (! $user) {
                $message = $looksLikeEmail
                    ? 'No fue posible autenticar la cuenta interna y tampoco se encontro el usuario en LDAP.'
                    : 'No se encontro el usuario en el directorio LDAP.';

                $this->addError('identifier', $message);
                $audit->log('authentication', 'security.admin.login.ldap.not_found', 'failed', $message, null, null, 'security', ['identifier' => $identifier]);

                return;
            }

            if ($user->organization?->isSuspended()) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
                $message = 'La organización de esta cuenta está suspendida y el acceso administrativo fue rechazado.';
                $this->addError('identifier', $message);
                $audit->log('authentication', 'security.admin.login.ldap.suspended_tenant_denied', 'warning', $message, $user, $user, 'security', [
                    'identifier' => $identifier,
                    'organization_id' => $user->organization_id,
                ]);

                return;
            }

            if (! $authorization->canAccessAdminPanel($user)) {
                $message = 'La cuenta LDAP es valida, pero no tiene acceso administrativo.';
                $this->addError('identifier', $message);
                $audit->log('authentication', 'security.admin.login.ldap.denied', 'warning', $message, $user, $user, 'security', ['identifier' => $identifier]);

                return;
            }

            Auth::login($user, $this->remember);
            request()->session()->regenerate();
            $audit->log('authentication', 'security.admin.login.ldap.success', 'success', 'Ingreso administrativo por LDAP.', $user, $user, 'security', ['identifier' => $identifier]);
            $this->redirectIntended(route('admin.dashboard'), navigate: false);

            return;
        }

        if (! $looksLikeEmail) {
            $message = 'Ingresa un correo para cuentas internas o habilita LDAP para usar un usuario sin dominio.';
            $this->addError('identifier', $message);
            $audit->log('authentication', 'security.admin.login.identifier.invalid', 'failed', $message, null, null, 'security', ['identifier' => $identifier]);

            return;
        }

        $message = 'Credenciales internas invalidas.';
        $this->addError('identifier', $message);
        $audit->log('authentication', 'security.admin.login.internal.failed', 'failed', $message, null, null, 'security', ['identifier' => $identifier]);
    }

    public function render(SecurityAuthSettingsService $settingsService)
    {
        return view('security::auth.livewire.admin-login-screen', [
            'authSettings' => $settingsService->getForView(),
        ]);
    }

    private function redirectAuthenticatedUser(SecurityAuthorizationService $authorization): void
    {
        $target = $authorization->canAccessAdminPanel(Auth::user()) ? route('admin.dashboard') : route('home');

        $this->redirectIntended($target, navigate: false);
    }

    private function attemptInternalLogin(
        string $email,
        string $password,
        SecurityAuthorizationService $authorization,
        SecurityAuditService $audit,
        OrganizationContextService $organizationContext,
    ): bool {
        if (! Auth::attempt([
            'email' => $email,
            'password' => $password,
            'organization_id' => $organizationContext->currentOrganizationId(),
        ], $this->remember)) {
            return false;
        }

        if (Auth::user()?->organization?->isSuspended()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            $message = 'La organización de esta cuenta está suspendida y no puede ingresar al panel.';
            $this->addError('identifier', $message);
            $audit->log('authentication', 'security.admin.login.internal.suspended_tenant_denied', 'warning', $message, null, null, 'security', [
                'identifier' => $email,
                'organization_id' => $organizationContext->currentOrganizationId(),
            ]);

            return true;
        }

        if (! $authorization->canAccessAdminPanel(Auth::user())) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            $message = 'Tu cuenta no tiene acceso al panel administrativo.';
            $this->addError('identifier', $message);
            $audit->log('authentication', 'security.admin.login.internal.denied', 'warning', $message, null, null, 'security', ['identifier' => $email]);

            return true;
        }

        request()->session()->regenerate();
        $audit->log('authentication', 'security.admin.login.internal.success', 'success', 'Ingreso administrativo interno correcto.', Auth::user(), Auth::user(), 'security', ['identifier' => $email]);
        $this->redirectIntended(route('admin.dashboard'), navigate: false);

        return true;
    }
}

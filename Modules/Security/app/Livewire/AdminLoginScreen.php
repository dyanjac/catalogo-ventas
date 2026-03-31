<?php

namespace Modules\Security\Livewire;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContextService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\Commerce\Entities\CommerceSetting;
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

    public string $selectedOrganizationSlug = '';

    /**
     * @var array<int,array{slug:string,name:string,code:string}>
     */
    public array $organizationOptions = [];

    public function mount(SecurityAuthorizationService $authorization, OrganizationContextService $organizationContext): void
    {
        if (Auth::check()) {
            $this->redirectAuthenticatedUser($authorization);
        }

        $organization = $organizationContext->explicit();

        if ($organization) {
            $this->selectedOrganizationSlug = $organization->slug;
            $this->organizationOptions = [$this->mapOrganizationOption($organization)];
        }
    }

    public function updatedIdentifier(): void
    {
        if (trim($this->identifier) === '') {
            $this->organizationOptions = [];
        }
    }

    public function identifyOrganization(OrganizationContextService $organizationContext): void
    {
        $this->resetErrorBag('identifier');
        $this->resetErrorBag('selectedOrganizationSlug');

        $identifier = trim($this->identifier);

        if ($identifier === '') {
            $this->addError('identifier', 'Ingresa primero tu correo para identificar la organización.');

            return;
        }

        $organizations = $this->resolveOrganizationsForIdentifier($identifier);
        $this->organizationOptions = $organizations->map(fn (Organization $organization) => $this->mapOrganizationOption($organization))->all();

        if ($organizations->isEmpty()) {
            $organizationContext->clearExplicit();
            $this->selectedOrganizationSlug = '';
            $this->addError('identifier', 'No se encontraron organizaciones activas para ese correo.');

            return;
        }

        if ($organizations->count() === 1) {
            $organization = $organizations->first();
            $this->selectedOrganizationSlug = (string) $organization->slug;
            $organizationContext->rememberExplicit($this->selectedOrganizationSlug);

            return;
        }

        $this->selectedOrganizationSlug = '';
        $organizationContext->clearExplicit();
        $this->addError('selectedOrganizationSlug', 'Selecciona una organización para continuar con el acceso.');
    }

    public function selectOrganization(string $slug, OrganizationContextService $organizationContext): void
    {
        $organization = $organizationContext->rememberExplicit($slug);

        if (! $organization) {
            $this->selectedOrganizationSlug = '';
            $this->addError('selectedOrganizationSlug', 'No se pudo resolver la organización seleccionada.');

            return;
        }

        $this->selectedOrganizationSlug = $organization->slug;
        $this->resetErrorBag('selectedOrganizationSlug');
    }

    public function clearOrganizationSelection(OrganizationContextService $organizationContext): void
    {
        $this->selectedOrganizationSlug = '';
        $this->organizationOptions = [];
        $organizationContext->clearExplicit();
        $this->resetErrorBag('selectedOrganizationSlug');
    }

    public function login(SecurityAuthorizationService $authorization, SecurityAuditService $audit, OrganizationContextService $organizationContext): void
    {
        $settings = app(SecurityAuthSettingsService::class)->getForView();
        $ldapEnabled = (bool) ($settings['ldap_enabled'] ?? false);

        $credentials = $this->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $identifier = trim($credentials['identifier']);
        $looksLikeEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $organization = $this->resolvedOrganization($organizationContext);

        if (! $organization) {
            if ($looksLikeEmail) {
                $this->identifyOrganization($organizationContext);
            }

            if (! $this->resolvedOrganization($organizationContext)) {
                $this->addError('selectedOrganizationSlug', 'Primero identifica y selecciona la organización para continuar.');

                return;
            }

            $organization = $this->resolvedOrganization($organizationContext);
        }

        if ($organization?->isSuspended()) {
            $message = 'La organización seleccionada está suspendida y no permite ingresos al panel administrativo.';
            $this->addError('identifier', $message);
            $audit->log('authentication', 'security.admin.login.suspended_tenant_denied', 'warning', $message, null, null, 'security', [
                'organization_id' => $organization->id,
                'organization_code' => $organization->code,
                'identifier' => $identifier,
            ]);

            return;
        }

        if ($looksLikeEmail && $this->attemptInternalLogin($identifier, $credentials['password'], $authorization, $audit, $organization)) {
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

            if ((int) $user->organization_id !== (int) $organization->id) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
                $message = 'La cuenta autenticada no pertenece a la organización seleccionada.';
                $this->addError('identifier', $message);
                $audit->log('authentication', 'security.admin.login.ldap.organization_mismatch', 'warning', $message, $user, $user, 'security', [
                    'identifier' => $identifier,
                    'selected_organization_id' => $organization->id,
                    'user_organization_id' => $user->organization_id,
                ]);

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
            $message = 'Primero selecciona la organización y luego usa un correo o un usuario LDAP valido.';
            $this->addError('identifier', $message);
            $audit->log('authentication', 'security.admin.login.identifier.invalid', 'failed', $message, null, null, 'security', ['identifier' => $identifier]);

            return;
        }

        $message = 'Credenciales internas invalidas.';
        $this->addError('identifier', $message);
        $audit->log('authentication', 'security.admin.login.internal.failed', 'failed', $message, null, null, 'security', ['identifier' => $identifier]);
    }

    public function render(SecurityAuthSettingsService $settingsService, OrganizationContextService $organizationContext)
    {
        $organization = $this->resolvedOrganization($organizationContext);

        return view('security::auth.livewire.admin-login-screen', [
            'authSettings' => $settingsService->getForView(),
            'resolvedOrganization' => $organization,
            'commerce' => $this->resolveCommerceData($organization),
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
        Organization $organization,
    ): bool {
        if (! Auth::attempt([
            'email' => Str::lower($email),
            'password' => $password,
            'organization_id' => $organization->id,
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
                'organization_id' => $organization->id,
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

    /**
     * @return Collection<int,Organization>
     */
    private function resolveOrganizationsForIdentifier(string $identifier): Collection
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) === false) {
            return collect();
        }

        $organizationIds = User::query()
            ->where('email', Str::lower($identifier))
            ->where('is_active', true)
            ->whereNotNull('organization_id')
            ->pluck('organization_id')
            ->filter()
            ->unique()
            ->values();

        if ($organizationIds->isEmpty()) {
            return collect();
        }

        return Organization::query()
            ->whereIn('id', $organizationIds->all())
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function resolvedOrganization(OrganizationContextService $organizationContext): ?Organization
    {
        if ($this->selectedOrganizationSlug !== '') {
            return $organizationContext->rememberExplicit($this->selectedOrganizationSlug);
        }

        return $organizationContext->explicit();
    }

    /**
     * @return array{name:string,email:string,phone:string,tax_id:string,logo_url:?string}
     */
    private function resolveCommerceData(?Organization $organization): array
    {
        if (! $organization) {
            return [
                'name' => 'Selecciona tu organización',
                'email' => '',
                'phone' => '',
                'tax_id' => '',
                'logo_url' => null,
            ];
        }

        $setting = CommerceSetting::query()->where('organization_id', $organization->id)->first();

        return [
            'name' => $setting?->company_name ?: $organization->name,
            'email' => (string) ($setting?->email ?: ''),
            'phone' => (string) ($setting?->phone ?: ''),
            'tax_id' => (string) ($setting?->tax_id ?: $organization->tax_id ?: ''),
            'logo_url' => $setting?->logo_url,
        ];
    }

    /**
     * @return array{slug:string,name:string,code:string}
     */
    private function mapOrganizationOption(Organization $organization): array
    {
        return [
            'slug' => (string) $organization->slug,
            'name' => (string) $organization->name,
            'code' => (string) $organization->code,
        ];
    }
}

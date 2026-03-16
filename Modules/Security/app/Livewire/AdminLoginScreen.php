<?php

namespace Modules\Security\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Security\Services\LdapDirectoryService;
use Modules\Security\Services\SecurityAuthSettingsService;
use Throwable;

class AdminLoginScreen extends Component
{
    public string $identifier = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirectAuthenticatedUser();
        }
    }

    public function login(): void
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

        if ($looksLikeEmail && $this->attemptInternalLogin($identifier, $credentials['password'])) {
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

                if ($looksLikeEmail) {
                    $this->addError('identifier', 'Credenciales internas invalidas y LDAP respondio: '.$exception->getMessage());
                } else {
                    $this->addError('identifier', $exception->getMessage() !== '' ? $exception->getMessage() : 'No se pudo autenticar contra LDAP.');
                }

                return;
            }

            if (! $user) {
                $this->addError('identifier', $looksLikeEmail
                    ? 'No fue posible autenticar la cuenta interna y tampoco se encontro el usuario en LDAP.'
                    : 'No se encontro el usuario en el directorio LDAP.');

                return;
            }

            if (! $user->isSuperAdmin()) {
                $this->addError('identifier', 'La cuenta LDAP es valida, pero no tiene acceso administrativo.');

                return;
            }

            Auth::login($user, $this->remember);
            request()->session()->regenerate();
            $this->redirectIntended(route('admin.dashboard'), navigate: false);

            return;
        }

        if (! $looksLikeEmail) {
            $this->addError('identifier', 'Ingresa un correo para cuentas internas o habilita LDAP para usar un usuario sin dominio.');

            return;
        }

        $this->addError('identifier', 'Credenciales internas invalidas.');
    }

    public function render(SecurityAuthSettingsService $settingsService)
    {
        return view('security::auth.livewire.admin-login-screen', [
            'authSettings' => $settingsService->getForView(),
        ]);
    }

    private function redirectAuthenticatedUser(): void
    {
        $target = Auth::user()?->isSuperAdmin() ? route('admin.dashboard') : route('home');

        $this->redirectIntended($target, navigate: false);
    }

    private function attemptInternalLogin(string $email, string $password): bool
    {
        if (! Auth::attempt([
            'email' => $email,
            'password' => $password,
        ], $this->remember)) {
            return false;
        }

        if (! Auth::user()?->isSuperAdmin()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            $this->addError('identifier', 'Tu cuenta no tiene acceso al panel administrativo.');

            return true;
        }

        request()->session()->regenerate();
        $this->redirectIntended(route('admin.dashboard'), navigate: false);

        return true;
    }
}

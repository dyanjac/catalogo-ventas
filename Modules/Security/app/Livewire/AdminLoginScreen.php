<?php

namespace Modules\Security\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AdminLoginScreen extends Component
{
    public string $email = '';

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
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $this->remember)) {
            $this->addError('email', 'Credenciales invalidas.');

            return;
        }

        if (! Auth::user()?->isSuperAdmin()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
            $this->addError('email', 'Tu cuenta no tiene acceso al panel administrativo.');

            return;
        }

        request()->session()->regenerate();
        $this->redirectIntended(route('admin.dashboard'), navigate: false);
    }

    public function render()
    {
        return view('security::auth.livewire.admin-login-screen');
    }

    private function redirectAuthenticatedUser(): void
    {
        $target = Auth::user()?->isSuperAdmin() ? route('admin.dashboard') : route('home');

        $this->redirectIntended($target, navigate: false);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OrganizationContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Models\SecurityRole;
use Modules\Security\Services\SecurityAuditService;
use Modules\Security\Services\SecurityAuthorizationService;

class AuthController extends Controller
{
    public function showLogin(SecurityAuthorizationService $authorization)
    {
        if (Auth::check()) {
            $target = $authorization->canAccessAdminPanel(Auth::user()) ? route('admin.dashboard') : route('home');

            return redirect()->intended($target);
        }

        return view('auth.login');
    }

    public function login(
        Request $request,
        SecurityAuthorizationService $authorization,
        OrganizationContextService $organizationContext,
        SecurityAuditService $audit,
    ) {
        $credentials = $request->validateWithBag('login', [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $organization = $organizationContext->current();

        if ($organization?->isSuspended()) {
            $message = 'La organización actual está suspendida y no permite nuevos inicios de sesión.';
            $audit->log('authentication', 'security.customer.login.suspended_tenant_denied', 'warning', $message, null, null, 'security', [
                'organization_id' => $organization->id,
                'organization_code' => $organization->code,
                'email' => $credentials['email'],
            ]);

            return back()
                ->withErrors(['email' => $message], 'login')
                ->withInput($request->only('email'))
                ->with('openAuthModal', 'login');
        }

        $organizationId = $organization?->id;

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'organization_id' => $organizationId,
        ], (bool) $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Credenciales inválidas.'], 'login')
                ->withInput($request->only('email'))
                ->with('openAuthModal', 'login');
        }

        $request->session()->regenerate();

        $target = $authorization->canAccessAdminPanel(Auth::user()) ? route('admin.dashboard') : route('home');

        return redirect()->intended($target)
            ->with('success', 'Sesión iniciada correctamente.');
    }

    public function register(Request $request, OrganizationContextService $organizationContext, SecurityAuditService $audit)
    {
        $organization = $organizationContext->current();
        $organizationId = $organization?->id;

        if ($organization?->isSuspended()) {
            $message = 'La organización actual está suspendida y no permite nuevos registros.';
            $audit->log('authentication', 'security.customer.register.suspended_tenant_denied', 'warning', $message, null, null, 'security', [
                'organization_id' => $organization->id,
                'organization_code' => $organization->code,
                'email' => $request->input('email'),
            ]);

            return back()
                ->withErrors(['register' => $message], 'register')
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $data = $request->validateWithBag('register', [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->where('organization_id', $organizationId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'document_type' => ['nullable', Rule::in(['dni', 'ruc', 'ce', 'pasaporte'])],
            'document_number' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $defaultBranchId = SecurityBranch::query()->forCurrentOrganization()->where('is_default', true)->value('id');

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'role' => 'customer',
            'organization_id' => $organizationId,
            'branch_id' => $defaultBranchId,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        if ($customerRole = SecurityRole::query()->where('code', 'customer')->first()) {
            $user->roles()->syncWithoutDetaching([
                $customerRole->id => [
                    'scope' => 'all',
                    'is_active' => true,
                    'context' => null,
                ],
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))
            ->with('success', 'Cuenta creada e inicio de sesión exitoso.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Sesión cerrada.');
    }
}

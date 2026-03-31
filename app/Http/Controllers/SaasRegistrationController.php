<?php

namespace App\Http\Controllers;

use App\Services\OrganizationProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaasRegistrationController extends Controller
{
    public function create(): View
    {
        return view('saas.register');
    }

    public function store(Request $request, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:160'],
            'organization_code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('organizations', 'code')],
            'organization_slug' => ['required', 'string', 'max:160', 'alpha_dash', Rule::unique('organizations', 'slug')],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['required', 'email', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'branch_name' => ['required', 'string', 'max:120'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $result = $provisioning->provisionDemoOrganization($data + [
            'provisioned_via' => 'public_saas_registration',
        ]);

        return redirect()
            ->route('saas.register.create')
            ->with('success', 'Tu organizacion fue creada en entorno DEMO. Ya puedes ingresar al panel administrativo con las credenciales iniciales.')
            ->with('provisioned_credentials', [
                'organization' => $result['organization']->name,
                'admin_email' => $result['admin']->email,
                'generated_password' => $result['password'],
                'admin_login_url' => route('admin.login', ['org' => $result['organization']->slug]),
            ]);
    }
}

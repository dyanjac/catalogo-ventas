<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrganizationProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Security\Services\SecurityAuditService;
use Modules\Security\Services\SecurityAuthorizationService;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly SecurityAuthorizationService $authorization,
        private readonly SecurityAuditService $auditService,
    ) {
    }

    public function index(): View
    {
        $this->ensureSuperAdmin();

        return view('admin.organizations.index', [
            'organizations' => Organization::query()->latest('id')->paginate(15),
        ]);
    }

    public function create(): View
    {
        $this->ensureSuperAdmin();

        return view('admin.organizations.create');
    }

    public function store(Request $request, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $this->ensureSuperAdmin();

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

        $result = $provisioning->provisionDemoOrganization($data);

        return redirect()
            ->route('admin.organizations.show', $result['organization'])
            ->with('success', 'Organización creada en entorno DEMO correctamente.')
            ->with('provisioned_credentials', [
                'organization' => $result['organization']->name,
                'admin_email' => $result['admin']->email,
                'generated_password' => $result['password'],
            ]);
    }

    public function show(Organization $organization, OrganizationProvisioningService $provisioning): View
    {
        $this->ensureSuperAdmin();

        $organization->load(['branches', 'users']);
        $commerceSetting = CommerceSetting::query()->where('organization_id', $organization->id)->first();

        return view('admin.organizations.show', [
            'organization' => $organization,
            'commerceSetting' => $commerceSetting,
            'productionChecks' => $provisioning->productionReadinessChecks($organization),
        ]);
    }

    public function update(Request $request, Organization $organization, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:160'],
            'organization_code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('organizations', 'code')->ignore($organization->id)],
            'organization_slug' => ['required', 'string', 'max:160', 'alpha_dash', Rule::unique('organizations', 'slug')->ignore($organization->id)],
            'company_name' => ['required', 'string', 'max:160'],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'tax_id' => [Rule::requiredIf($organization->environment === 'production'), 'nullable', 'string', 'max:30'],
            'contact_email' => ['required', 'email', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        if ($organization->environment === 'production' && blank($data['tax_id'] ?? null)) {
            throw ValidationException::withMessages([
                'tax_id' => 'No puedes dejar sin RUC o Tax ID a una organización que ya está en PRODUCTION.',
            ]);
        }

        $organization = $provisioning->updateOrganizationProfile($organization, $data);

        $this->auditService->log(
            eventType: 'configuration',
            eventCode: 'saas.organization.updated',
            result: 'success',
            message: 'Datos base de organización actualizados.',
            actor: auth()->user(),
            target: $organization,
            module: 'security',
            context: [
                'organization_id' => $organization->id,
                'organization_code' => $organization->code,
            ],
        );

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', 'Los datos base de la organización fueron actualizados.');
    }

    public function updatePrimaryBranch(Request $request, Organization $organization, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $currentBranch = $organization->branches()->where('is_default', true)->first();

        $data = $request->validate([
            'branch_name' => ['required', 'string', 'max:120'],
            'branch_code' => [
                'required',
                'string',
                'max:40',
                'alpha_dash',
                Rule::unique('security_branches', 'code')
                    ->where(fn ($query) => $query->where('organization_id', $organization->id))
                    ->ignore($currentBranch?->id),
            ],
            'branch_city' => ['nullable', 'string', 'max:100'],
            'branch_address' => ['nullable', 'string', 'max:200'],
            'branch_phone' => ['nullable', 'string', 'max:30'],
            'branch_is_active' => ['nullable', 'boolean'],
        ]);

        if ($organization->environment === 'production' && ! (bool) ($data['branch_is_active'] ?? false)) {
            throw ValidationException::withMessages([
                'branch_is_active' => 'No puedes desactivar la sucursal principal de una organización en PRODUCTION.',
            ]);
        }

        $branch = $provisioning->updatePrimaryBranch($organization, $data);

        $this->auditService->log(
            eventType: 'configuration',
            eventCode: 'saas.organization.primary_branch_updated',
            result: 'success',
            message: 'Sucursal principal actualizada.',
            actor: auth()->user(),
            target: $branch,
            module: 'security',
            context: [
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'branch_code' => $branch->code,
            ],
        );

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', 'La sucursal principal fue actualizada.');
    }

    public function recoverPrimaryBranch(Organization $organization, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $branch = $provisioning->recoverPrimaryBranch($organization);

        $this->auditService->log(
            eventType: 'configuration',
            eventCode: 'saas.organization.primary_branch_recovered',
            result: 'success',
            message: 'Sucursal principal reconstruida.',
            actor: auth()->user(),
            target: $branch,
            module: 'security',
            context: [
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'branch_code' => $branch->code,
            ],
        );

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', 'La sucursal principal fue reconstruida con valores base.');
    }

    public function updateInitialAdmin(Request $request, Organization $organization, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $currentAdmin = $organization->users()
            ->whereHas('roles', fn ($query) => $query->where('code', 'super_admin'))
            ->orderBy('id')
            ->first() ?? $organization->users()->orderBy('id')->first();

        $data = $request->validate([
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $query->where('organization_id', $organization->id))
                    ->ignore($currentAdmin?->id),
            ],
            'admin_phone' => ['nullable', 'string', 'max:30'],
            'admin_is_active' => ['nullable', 'boolean'],
            'admin_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($organization->environment === 'production' && ! (bool) ($data['admin_is_active'] ?? false)) {
            throw ValidationException::withMessages([
                'admin_is_active' => 'No puedes desactivar el administrador inicial de una organización en PRODUCTION.',
            ]);
        }

        $admin = $provisioning->updateInitialAdmin($organization, $data);

        $this->auditService->log(
            eventType: 'configuration',
            eventCode: 'saas.organization.initial_admin_updated',
            result: 'success',
            message: 'Administrador inicial actualizado.',
            actor: auth()->user(),
            target: $admin,
            module: 'security',
            context: [
                'organization_id' => $organization->id,
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
                'password_reset' => filled($data['admin_password'] ?? null),
            ],
        );

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', filled($data['admin_password'] ?? null)
                ? 'El administrador inicial fue actualizado y su contraseña fue renovada.'
                : 'El administrador inicial fue actualizado.');
    }

    public function recoverInitialAdmin(Organization $organization, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $this->ensureSuperAdmin();

        $result = $provisioning->recoverInitialAdmin($organization);
        $admin = $result['admin'];

        $this->auditService->log(
            eventType: 'configuration',
            eventCode: 'saas.organization.initial_admin_recovered',
            result: 'success',
            message: 'Administrador inicial reconstruido.',
            actor: auth()->user(),
            target: $admin,
            module: 'security',
            context: [
                'organization_id' => $organization->id,
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
            ],
        );

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', 'El administrador inicial fue reconstruido con credenciales nuevas.')
            ->with('recovered_admin_credentials', [
                'admin_email' => $admin->email,
                'generated_password' => $result['password'],
            ]);
    }

    public function suspendOrganization(Organization $organization, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $this->ensureSuperAdmin();

        if ($organization->is_default) {
            throw ValidationException::withMessages([
                'organization' => 'No puedes suspender la organización marcada como default.',
            ]);
        }

        if ($organization->status === 'suspended') {
            return redirect()
                ->route('admin.organizations.show', $organization)
                ->with('success', 'La organización ya se encuentra suspendida.');
        }

        $organization = $provisioning->suspendOrganization($organization);

        $this->auditService->log(
            eventType: 'configuration',
            eventCode: 'saas.organization.suspended',
            result: 'success',
            message: 'Organización suspendida.',
            actor: auth()->user(),
            target: $organization,
            module: 'security',
            context: [
                'organization_id' => $organization->id,
                'organization_code' => $organization->code,
                'status' => $organization->status,
            ],
        );

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', 'La organización fue suspendida.');
    }

    public function reactivateOrganization(Organization $organization, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $this->ensureSuperAdmin();

        if ($organization->status === 'active') {
            return redirect()
                ->route('admin.organizations.show', $organization)
                ->with('success', 'La organización ya se encuentra activa.');
        }

        $organization = $provisioning->reactivateOrganization($organization);

        $this->auditService->log(
            eventType: 'configuration',
            eventCode: 'saas.organization.reactivated',
            result: 'success',
            message: 'Organización reactivada.',
            actor: auth()->user(),
            target: $organization,
            module: 'security',
            context: [
                'organization_id' => $organization->id,
                'organization_code' => $organization->code,
                'status' => $organization->status,
            ],
        );

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', 'La organización fue reactivada.');
    }

    public function activateProduction(Organization $organization, OrganizationProvisioningService $provisioning): RedirectResponse
    {
        $this->ensureSuperAdmin();

        if ($organization->environment === 'production') {
            return redirect()
                ->route('admin.organizations.show', $organization)
                ->with('success', 'La organización ya se encuentra en entorno PRODUCTION.');
        }

        $checks = $provisioning->productionReadinessChecks($organization);
        $failedChecks = collect($checks)->where('ok', false)->values()->all();

        if ($failedChecks !== []) {
            return redirect()
                ->route('admin.organizations.show', $organization)
                ->with('error', 'La organización no cumple las validaciones mínimas para pasar a producción.')
                ->with('production_failed_checks', $failedChecks);
        }

        $organization = $provisioning->activateProduction($organization);

        $this->auditService->log(
            eventType: 'configuration',
            eventCode: 'saas.organization.production_activated',
            result: 'success',
            message: 'Organización promovida de DEMO a PRODUCTION.',
            actor: auth()->user(),
            target: $organization,
            module: 'security',
            context: [
                'organization_id' => $organization->id,
                'organization_code' => $organization->code,
                'environment' => $organization->environment,
                'activated_at' => data_get($organization->settings_json, 'production_activated_at'),
            ],
        );

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('success', 'La organización quedó habilitada en entorno PRODUCTION.');
    }

    private function ensureSuperAdmin(): void
    {
        abort_unless($this->authorization->hasRole(auth()->user(), 'super_admin'), 403);
    }
}

<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Security\Services\SecurityScopeService;

class CustomerController extends Controller
{
    public function __construct(private readonly OrganizationContextService $organizationContext)
    {
    }

    public function index(): View
    {
        return view('admin.customers.index');
    }

    public function show(User $customer, SecurityScopeService $scopeService): View
    {
        abort_unless($scopeService->canAccessUser(request()->user(), $customer, 'customers'), 403);

        $customer->load([
            'orders' => fn ($query) => $query->forCurrentOrganization()->latest()->take(10),
            'roles' => fn ($query) => $query->wherePivot('is_active', true)->orderBy('name'),
        ]);

        return view('admin.customers.show', compact('customer'));
    }

    public function update(Request $request, User $customer, SecurityScopeService $scopeService): RedirectResponse
    {
        abort_unless($scopeService->canAccessUser($request->user(), $customer, 'customers'), 403);

        if ($customer->organization()->first()?->isSuspended() || $this->organizationContext->isSuspended()) {
            throw ValidationException::withMessages([
                'customer' => 'La organización asociada al cliente está suspendida y no permite mantenimiento administrativo.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)->where('organization_id', $customer->organization_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'document_type' => ['nullable', 'in:dni,ruc,ce,pasaporte'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        $customer->update($data);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'Cliente actualizado correctamente. Los roles se administran desde Seguridad > Accesos de usuarios.');
    }
}

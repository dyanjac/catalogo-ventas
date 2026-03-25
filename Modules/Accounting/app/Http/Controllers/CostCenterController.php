<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Models\AccountingCostCenter;
use Modules\Accounting\Services\AccountingAuditService;

class CostCenterController extends Controller
{
    public function __construct(
        private readonly AccountingAuditService $audit,
        private readonly OrganizationContextService $organizationContext
    ) {
    }

    public function index(): View
    {
        return view('accounting::cost-centers.index', [
            'costCenters' => AccountingCostCenter::query()->forCurrentOrganization()->orderBy('code')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationContext->currentOrganizationId();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('accounting_cost_centers', 'code')->where('organization_id', $organizationId)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $costCenter = AccountingCostCenter::query()->create([
            ...$data,
            'organization_id' => $organizationId,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        $this->audit->log('cost_center', (int) $costCenter->id, 'create', $costCenter->toArray());

        return back()->with('success', 'Centro de costo creado correctamente.');
    }

    public function update(Request $request, AccountingCostCenter $costCenter): RedirectResponse
    {
        $organizationId = $this->organizationContext->currentOrganizationId();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('accounting_cost_centers', 'code')->where('organization_id', $organizationId)->ignore($costCenter->id)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $costCenter->toArray();
        $costCenter->update([
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        $this->audit->log('cost_center', (int) $costCenter->id, 'update', ['before' => $before, 'after' => $costCenter->fresh()->toArray()]);

        return back()->with('success', 'Centro de costo actualizado correctamente.');
    }
}

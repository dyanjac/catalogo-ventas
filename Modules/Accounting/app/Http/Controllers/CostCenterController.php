<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\AccountingCostCenter;
use Modules\Accounting\Services\AccountingAuditService;

class CostCenterController extends Controller
{
    public function __construct(private readonly AccountingAuditService $audit)
    {
    }

    public function index(): View
    {
        return view('accounting::cost-centers.index', [
            'costCenters' => AccountingCostCenter::query()->orderBy('code')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:accounting_cost_centers,code'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $costCenter = AccountingCostCenter::query()->create([
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        $this->audit->log('cost_center', (int) $costCenter->id, 'create', $costCenter->toArray());

        return back()->with('success', 'Centro de costo creado correctamente.');
    }

    public function update(Request $request, AccountingCostCenter $costCenter): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:accounting_cost_centers,code,' . $costCenter->id],
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

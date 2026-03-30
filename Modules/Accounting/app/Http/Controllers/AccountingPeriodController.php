<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Accounting\Services\AccountingAuditService;

class AccountingPeriodController extends Controller
{
    public function __construct(
        private readonly AccountingAuditService $audit,
        private readonly OrganizationContextService $organizationContext
    ) {
    }

    public function index(): View
    {
        return view('accounting::periods.index', [
            'periods' => AccountingPeriod::query()->forCurrentOrganization()->latest('year')->latest('month')->paginate(24),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureTenantOperational();
        $organizationId = $this->organizationContext->currentOrganizationId();

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('accounting_periods', 'month')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('year', $request->integer('year'))),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:open,closed'],
        ]);

        $period = AccountingPeriod::query()->create([
            ...$data,
            'organization_id' => $organizationId,
            'closed_at' => $data['status'] === 'closed' ? now() : null,
            'closed_by' => $data['status'] === 'closed' ? auth()->id() : null,
        ]);

        $this->audit->log('period', (int) $period->id, 'create', $period->toArray());

        return back()->with('success', 'Periodo contable creado correctamente.');
    }

    public function update(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $this->ensureTenantOperational();
        $organizationId = $this->organizationContext->currentOrganizationId();

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('accounting_periods', 'month')
                    ->ignore($period->id)
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('year', $request->integer('year'))),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:open,closed'],
        ]);

        $before = $period->toArray();
        $period->update([
            'year' => (int) $data['year'],
            'month' => (int) $data['month'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => $data['status'],
            'closed_at' => $data['status'] === 'closed' ? now() : null,
            'closed_by' => $data['status'] === 'closed' ? auth()->id() : null,
        ]);

        $this->audit->log('period', (int) $period->id, 'update', ['before' => $before, 'after' => $period->fresh()->toArray()]);

        return back()->with('success', 'Periodo contable actualizado correctamente.');
    }

    private function ensureTenantOperational(): void
    {
        if (! $this->organizationContext->isSuspended()) {
            return;
        }

        throw ValidationException::withMessages([
            'accounting' => 'La organización actual está suspendida y no permite cambios contables.',
        ]);
    }
}

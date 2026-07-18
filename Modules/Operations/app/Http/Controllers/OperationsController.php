<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Operations\Jobs\ProcessOperationalReconciliationJob;
use Modules\Operations\Models\OperationalIncident;
use Modules\Operations\Models\OperationalReconciliationRun;
use Modules\Operations\Services\OperationalIncidentService;

final class OperationsController extends Controller
{
    public function __construct(private readonly OrganizationContextService $organizations) {}

    public function index(): View
    {
        $organizationId = (int) $this->organizations->currentOrganizationId();

        return view('operations::index', [
            'latestRun' => OperationalReconciliationRun::query()->where('organization_id', $organizationId)->latest('id')->first(),
            'runs' => OperationalReconciliationRun::query()->where('organization_id', $organizationId)->latest('id')->paginate(20, ['*'], 'runs'),
            'incidents' => OperationalIncident::query()->where('organization_id', $organizationId)->whereIn('status', ['open', 'acknowledged'])->orderByRaw("CASE severity WHEN 'critical' THEN 0 ELSE 1 END")->latest('last_seen_at')->paginate(20, ['*'], 'incidents'),
        ]);
    }

    public function show(OperationalReconciliationRun $run): View
    {
        $run->load(['issues' => fn ($query) => $query->orderByRaw("CASE severity WHEN 'critical' THEN 0 ELSE 1 END")->orderBy('domain')]);

        return view('operations::show', compact('run'));
    }

    public function run(Request $request): RedirectResponse
    {
        $organizationId = (int) $this->organizations->currentOrganizationId();
        abort_if($this->organizations->isSuspended(), 423, 'La organización está suspendida.');
        ProcessOperationalReconciliationJob::dispatch($organizationId, 'manual', (int) $request->user()->id);

        return back()->with('success', 'Conciliación integral encolada.');
    }

    public function acknowledge(Request $request, OperationalIncident $incident, OperationalIncidentService $incidents): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $incidents->acknowledge($incident, (int) $request->user()->id, $data['note'] ?? null);

        return back()->with('success', 'Incidente reconocido; la evidencia permanece íntegra.');
    }

    public function metrics(): JsonResponse
    {
        $organizationId = (int) $this->organizations->currentOrganizationId();
        $latest = OperationalReconciliationRun::query()->where('organization_id', $organizationId)->latest('id')->first();

        return response()->json([
            'organization_id' => $organizationId,
            'generated_at' => now('UTC')->toIso8601String(),
            'latest_reconciliation' => $latest ? [
                'id' => $latest->id,
                'status' => $latest->status,
                'finished_at' => $latest->finished_at?->toIso8601String(),
                'duration_ms' => $latest->duration_ms,
                'issues' => $latest->issue_count,
                'critical' => $latest->critical_count,
                'warning' => $latest->warning_count,
                'metrics' => $latest->metrics,
            ] : null,
            'incidents' => [
                'open' => OperationalIncident::query()->where('organization_id', $organizationId)->where('status', 'open')->count(),
                'acknowledged' => OperationalIncident::query()->where('organization_id', $organizationId)->where('status', 'acknowledged')->count(),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Jobs\ProcessHistoricalAccountingActivationJob;
use Modules\Accounting\Models\AccountingActivationRun;
use Modules\Accounting\Services\HistoricalAccountingActivationService;
use Throwable;

final class AccountingActivationController extends Controller
{
    public function __construct(
        private readonly OrganizationContextService $organizationContext,
        private readonly HistoricalAccountingActivationService $activations,
    ) {}

    public function index(): View
    {
        $organizationId = $this->organizationId();
        $runs = AccountingActivationRun::query()->where('organization_id', $organizationId)->latest('id')->paginate(20);

        return view('accounting::activations.index', compact('runs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['cutoff_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $run = $this->activations->simulate($this->organizationId(), $data['cutoff_date'], (int) $request->user()->id);

        return redirect()->route('admin.accounting.activations.show', $run)
            ->with($run->status === 'blocked' ? 'warning' : 'success', $run->status === 'blocked'
                ? 'Simulación bloqueada: revise las inconsistencias antes de confirmar.'
                : 'Simulación terminada sin publicar eventos ni asientos.');
    }

    public function show(AccountingActivationRun $activation): View
    {
        $activation->load(['items' => fn ($query) => $query->orderBy('dependency_order')->orderBy('occurred_at')->orderBy('id')]);

        return view('accounting::activations.show', ['run' => $activation]);
    }

    public function confirm(Request $request, AccountingActivationRun $activation): RedirectResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'max:80'],
            'simulation_hash' => ['required', 'string', 'size:64'],
        ]);
        try {
            $run = $this->activations->confirm($activation, $data['confirmation'], $data['simulation_hash'], (int) $request->user()->id);
            try {
                ProcessHistoricalAccountingActivationJob::dispatch((int) $run->organization_id, (int) $run->id);
            } catch (Throwable $exception) {
                return back()->with('warning', 'La confirmación quedó guardada, pero no se pudo iniciar el worker: '.$exception->getMessage());
            }
        } catch (DomainException $exception) {
            return back()->withErrors(['confirmation' => $exception->getMessage()]);
        }

        return back()->with('success', 'Activación confirmada y enviada a procesamiento idempotente.');
    }

    public function reprocess(AccountingActivationRun $activation): RedirectResponse
    {
        abort_unless(in_array($activation->status, ['failed', 'confirmed'], true), 409, 'La corrida no requiere reproceso.');
        ProcessHistoricalAccountingActivationJob::dispatch((int) $activation->organization_id, (int) $activation->id);

        return back()->with('success', 'Reproceso solicitado con el mismo snapshot e identidades idempotentes.');
    }

    private function organizationId(): int
    {
        abort_if($this->organizationContext->isSuspended(), 423, 'La organización está suspendida.');
        $organizationId = $this->organizationContext->currentOrganizationId();
        abort_unless($organizationId, 409, 'Seleccione una organización activa.');

        return (int) $organizationId;
    }
}

<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Services\EconomicEventService;

class AccountingEconomicEventController extends Controller
{
    public function __construct(
        private readonly OrganizationContextService $organizationContext,
        private readonly EconomicEventService $events,
    ) {}

    public function index(Request $request): View
    {
        $status = trim((string) $request->input('status'));
        $type = trim((string) $request->input('type'));
        $search = trim((string) $request->input('search'));

        $events = AccountingEconomicEvent::query()
            ->forCurrentOrganization()
            ->with('entry:id,reference,status')
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($type !== '', fn (Builder $query) => $query->where('event_type', $type))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('source_code', 'like', "%{$search}%")
                ->orWhere('idempotency_key', 'like', "%{$search}%")))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('accounting::events.index', compact('events', 'status', 'type', 'search') + [
            'statuses' => EconomicEventStatus::cases(),
            'types' => EconomicEventType::cases(),
        ]);
    }

    public function show(AccountingEconomicEvent $event): View
    {
        $event->load(['entry.lines', 'reversalOf.entry']);

        return view('accounting::events.show', compact('event'));
    }

    public function process(AccountingEconomicEvent $event): RedirectResponse
    {
        abort_if($this->organizationContext->isSuspended(), 423, 'La organización está suspendida.');
        $entry = $event->status === EconomicEventStatus::Error
            ? tap($this->events->retry((int) $event->organization_id, (int) $event->id), fn () => null)->fresh()->entry
            : $this->events->process((int) $event->organization_id, (int) $event->id);

        return back()->with($entry ? 'success' : 'warning', $entry ? 'Evento procesado correctamente.' : 'El evento quedó pendiente o con error; revise su detalle.');
    }

    public function reverse(Request $request, AccountingEconomicEvent $event): RedirectResponse
    {
        abort_if($this->organizationContext->isSuspended(), 423, 'La organización está suspendida.');
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:160', Rule::unique('accounting_economic_events', 'idempotency_key')->where('organization_id', $event->organization_id)],
        ]);
        $reversal = $this->events->reverse($event, (string) $data['idempotency_key'], (int) $request->user()->id);

        return redirect()->route('admin.accounting.events.show', $reversal)->with('success', 'Reversión registrada sin alterar el asiento original.');
    }
}

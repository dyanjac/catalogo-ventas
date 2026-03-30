<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Accounting\Models\AccountingCostCenter;
use Modules\Accounting\Models\AccountingEntry;
use Modules\Accounting\Models\AccountingEntryAttachment;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Accounting\Services\AccountingAuditService;

class AccountingEntryController extends Controller
{
    public function __construct(
        private readonly AccountingAuditService $audit,
        private readonly OrganizationContextService $organizationContext
    ) {
    }

    public function index(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $status = trim((string) $request->input('status', ''));
        $search = trim((string) $request->input('search', ''));

        $entries = AccountingEntry::query()
            ->forCurrentOrganization()
            ->withCount('lines')
            ->when($year > 0, fn (Builder $q) => $q->where('period_year', $year))
            ->when($month > 0, fn (Builder $q) => $q->where('period_month', $month))
            ->when($status !== '', fn (Builder $q) => $q->where('status', $status))
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('reference', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('voucher_number', 'like', '%' . $search . '%')
                        ->orWhereHas('lines', fn (Builder $line) => $line->where('account_code', 'like', '%' . $search . '%'));
                });
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('accounting::entries.index', [
            'entries' => $entries,
            'year' => $year,
            'month' => $month,
            'status' => $status,
            'search' => $search,
            'statuses' => config('accounting.entry_statuses', []),
        ]);
    }

    public function edit(AccountingEntry $entry): View
    {
        $entry->load([
            'lines.costCenter' => fn ($query) => $query->forCurrentOrganization(),
            'attachments' => fn ($query) => $query->forCurrentOrganization(),
        ]);

        return view('accounting::entries.edit', [
            'entry' => $entry,
            'statuses' => config('accounting.entry_statuses', []),
            'costCenters' => AccountingCostCenter::query()->forCurrentOrganization()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, AccountingEntry $entry): RedirectResponse
    {
        $this->ensureTenantOperational();
        $organizationId = $this->organizationContext->currentOrganizationId();

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'voucher_type' => ['nullable', 'string', 'max:30'],
            'voucher_series' => ['nullable', 'string', 'max:20'],
            'voucher_number' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:' . implode(',', config('accounting.entry_statuses', ['draft', 'posted', 'voided']))],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_code' => ['required', 'string', 'max:40'],
            'lines.*.account_name' => ['nullable', 'string', 'max:160'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.line_description' => ['nullable', 'string', 'max:255'],
            'lines.*.cost_center_id' => ['nullable', Rule::exists('accounting_cost_centers', 'id')->where('organization_id', $organizationId)],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $entryDate = now()->parse($data['entry_date']);
        $period = AccountingPeriod::query()
            ->forCurrentOrganization()
            ->where('year', (int) $entryDate->year)
            ->where('month', (int) $entryDate->month)
            ->first();

        if ($period && $period->status === 'closed') {
            return back()
                ->withErrors(['entry_date' => 'El periodo contable seleccionado está cerrado y no permite modificaciones.'])
                ->withInput();
        }

        $lines = collect($data['lines'])
            ->map(function (array $line): array {
                return [
                    'account_code' => trim((string) $line['account_code']),
                    'account_name' => trim((string) ($line['account_name'] ?? '')) ?: null,
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'line_description' => trim((string) ($line['line_description'] ?? '')) ?: null,
                    'cost_center_id' => ! empty($line['cost_center_id']) ? (int) $line['cost_center_id'] : null,
                ];
            })
            ->filter(fn (array $line) => $line['account_code'] !== '' && ($line['debit'] > 0 || $line['credit'] > 0))
            ->values();

        if ($lines->isEmpty()) {
            return back()
                ->withErrors(['lines' => 'Debes registrar al menos una línea con débito o crédito mayor a cero.'])
                ->withInput();
        }

        $totalDebit = round((float) $lines->sum('debit'), 2);
        $totalCredit = round((float) $lines->sum('credit'), 2);

        if (abs($totalDebit - $totalCredit) > 0.0001) {
            return back()
                ->withErrors(['lines' => 'La partida doble no cuadra. El total Debe debe ser igual al total Haber.'])
                ->withInput();
        }

        $before = $entry->toArray();
        DB::transaction(function () use ($entry, $data, $entryDate, $lines, $totalDebit, $totalCredit, $organizationId): void {
            $entry->update([
                'entry_date' => $entryDate->toDateString(),
                'period_year' => (int) $entryDate->year,
                'period_month' => (int) $entryDate->month,
                'voucher_type' => $data['voucher_type'] ?? null,
                'voucher_series' => $data['voucher_series'] ?? null,
                'voucher_number' => $data['voucher_number'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_at' => $data['status'] === 'posted' ? now() : null,
            ]);

            $entry->lines()->delete();
            $entry->lines()->createMany($lines->map(fn (array $line) => [
                ...$line,
                'organization_id' => $organizationId,
            ])->all());
        });

        foreach (($request->file('attachments') ?? []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('accounting/entries/' . $entry->id, 'public');

            $entry->attachments()->create([
                'organization_id' => $organizationId,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $this->audit->log('entry', (int) $entry->id, 'update', [
            'before' => $before,
            'after' => $entry->fresh()->load('lines')->toArray(),
        ]);

        return redirect()
            ->route('admin.accounting.entries.edit', $entry)
            ->with('success', 'Asiento contable actualizado correctamente.');
    }

    public function destroyAttachment(AccountingEntry $entry, AccountingEntryAttachment $attachment): RedirectResponse
    {
        $this->ensureTenantOperational();

        if ((int) $attachment->accounting_entry_id !== (int) $entry->id) {
            abort(404);
        }

        Storage::disk('public')->delete($attachment->path);
        $payload = $attachment->toArray();
        $attachment->delete();

        $this->audit->log('entry_attachment', (int) $entry->id, 'delete', $payload);

        return back()->with('success', 'Adjunto eliminado correctamente.');
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

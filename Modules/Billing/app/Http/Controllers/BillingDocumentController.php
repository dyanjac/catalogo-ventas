<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Billing\Models\BillingDocument;

class BillingDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->input('status', ''));
        $provider = trim((string) $request->input('provider', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));
        $search = trim((string) $request->input('search', ''));

        $documents = BillingDocument::query()
            ->with('order')
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($provider !== '', fn (Builder $query) => $query->where('provider', $provider))
            ->when($dateFrom !== '', fn (Builder $query) => $query->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $query) => $query->whereDate('issue_date', '<=', $dateTo))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $sub) use ($search) {
                    $sub->where('series', 'like', '%'.$search.'%')
                        ->orWhere('number', 'like', '%'.$search.'%')
                        ->orWhere('customer_document_number', 'like', '%'.$search.'%')
                        ->orWhereHas('order', fn (Builder $order) => $order->where('id', $search));
                });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('billing::documents.index', [
            'documents' => $documents,
            'status' => $status,
            'provider' => $provider,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'providers' => config('billing.providers', []),
            'statuses' => ['draft', 'queued', 'issued', 'accepted', 'rejected', 'voided', 'error'],
        ]);
    }
}

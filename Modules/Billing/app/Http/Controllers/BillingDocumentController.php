<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\SimplePdfBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Services\ElectronicBillingService;

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
            ->with(['order', 'files'])
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

    public function show(BillingDocument $document): View
    {
        $document->load([
            'order.items.product',
            'files',
            'responseHistories' => fn ($query) => $query->latest('id'),
        ]);

        return view('billing::documents.show', [
            'document' => $document,
        ]);
    }

    public function redeclare(BillingDocument $document, ElectronicBillingService $electronicBilling): RedirectResponse
    {
        $payload = is_array($document->request_payload) ? $document->request_payload : [];

        if ($payload === [] || ! isset($payload['items']) || ! is_array($payload['items'])) {
            return back()->withErrors([
                'billing' => 'El comprobante no tiene payload válido para re-declarar al proveedor.',
            ]);
        }

        $result = $electronicBilling->issue($document, $payload);

        if (! (bool) ($result['ok'] ?? false)) {
            return back()->with('warning', 'Re-declaración enviada con error: '.($result['message'] ?? 'Error no especificado.'));
        }

        return back()->with('success', 'Re-declaración enviada correctamente al proveedor configurado.');
    }

    public function downloadXml(BillingDocument $document)
    {
        $file = $document->xmlFile();
        $path = $file?->storage_path ?? $document->xml_path ?: data_get($document->request_payload, 'xml_path');
        $disk = $file?->storage_disk ?? 'public';

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            abort(404, 'XML no disponible para este comprobante.');
        }

        return response()->download(
            Storage::disk($disk)->path($path),
            "{$document->series}-{$document->number}.xml",
            ['Content-Type' => 'application/xml']
        );
    }

    public function downloadCdr(BillingDocument $document)
    {
        $cdrFile = $document->cdrFile();
        if ($cdrFile && Storage::disk($cdrFile->storage_disk)->exists($cdrFile->storage_path)) {
            $extension = str_ends_with(strtolower($cdrFile->storage_path), '.zip') ? 'zip' : 'xml';

            return response()->download(
                Storage::disk($cdrFile->storage_disk)->path($cdrFile->storage_path),
                "R-{$document->series}-{$document->number}.{$extension}",
                ['Content-Type' => $cdrFile->mime_type ?: 'application/octet-stream']
            );
        }

        $responsePayload = is_array($document->response_payload) ? $document->response_payload : [];
        $path = data_get($responsePayload, 'cdr_path');

        if ($path && Storage::disk('public')->exists($path)) {
            return response()->download(
                Storage::disk('public')->path($path),
                "R-{$document->series}-{$document->number}.xml",
                ['Content-Type' => 'application/xml']
            );
        }

        $base64 = data_get($responsePayload, 'cdr_base64')
            ?? data_get($responsePayload, 'body.cdr_base64')
            ?? data_get($responsePayload, 'body.cdrZipBase64');

        if (is_string($base64) && $base64 !== '') {
            $decoded = base64_decode($base64, true);
            if ($decoded !== false) {
                return response($decoded, 200, [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename="R-'.$document->series.'-'.$document->number.'.zip"',
                ]);
            }
        }

        abort(404, 'CDR no disponible para este comprobante.');
    }

    public function downloadPdf(BillingDocument $document)
    {
        $items = collect(data_get($document->request_payload, 'items', []))
            ->map(function (array $row): string {
                $name = (string) ($row['name'] ?? '-');
                $qty = (float) ($row['quantity'] ?? 0);
                $price = (float) ($row['unit_price'] ?? 0);
                $subtotal = (float) ($row['line_subtotal'] ?? 0);

                return "{$name} | Cant: {$qty} | P.Unit: " . number_format($price, 2) . ' | Subt: ' . number_format($subtotal, 2);
            })
            ->all();

        $lines = [
            'Comprobante: ' . strtoupper((string) $document->document_type),
            'Numero: ' . $document->series . '-' . $document->number,
            'Fecha: ' . optional($document->issue_date)->format('d/m/Y'),
            'Cliente Doc: ' . ($document->customer_document_number ?: '-'),
            'Moneda: ' . $document->currency,
            'Subtotal: ' . number_format((float) $document->subtotal, 2),
            'IGV: ' . number_format((float) $document->tax, 2),
            'Total: ' . number_format((float) $document->total, 2),
            'Estado: ' . strtoupper((string) $document->status),
            '--- Detalle ---',
            ...$items,
        ];

        $pdf = SimplePdfBuilder::fromLines(
            'Comprobante electronico ' . $document->series . '-' . $document->number,
            $lines
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $document->series . '-' . $document->number . '.pdf"',
        ]);
    }
}

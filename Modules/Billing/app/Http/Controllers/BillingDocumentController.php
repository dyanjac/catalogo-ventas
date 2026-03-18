<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Services\ElectronicBillingService;
use Modules\ElectronicDocuments\Services\InvoicePdfService;
use Modules\Security\Services\SecurityScopeService;
use RuntimeException;

class BillingDocumentController extends Controller
{
    public function index(): View
    {
        return view('billing::documents.index');
    }

    public function show(BillingDocument $document, SecurityScopeService $scopeService): View
    {
        abort_unless($scopeService->canAccessBillingDocument(request()->user(), $document, 'billing'), 403);

        $document = $this->loadDocumentContext($document);

        return view('billing::documents.show', [
            'document' => $document,
        ]);
    }

    public function history(BillingDocument $document, SecurityScopeService $scopeService): View
    {
        abort_unless($scopeService->canAccessBillingDocument(request()->user(), $document, 'billing'), 403);

        $document = $this->loadDocumentContext($document);

        return view('billing::documents.history', [
            'document' => $document,
        ]);
    }

    public function redeclare(BillingDocument $document, ElectronicBillingService $electronicBilling, SecurityScopeService $scopeService): RedirectResponse
    {
        abort_unless($scopeService->canAccessBillingDocument(request()->user(), $document, 'billing'), 403);

        $payload = is_array($document->request_payload) ? $document->request_payload : [];

        if ($payload === [] || ! isset($payload['items']) || ! is_array($payload['items'])) {
            return back()->withErrors([
                'billing' => 'El comprobante no tiene payload válido para re-declarar al proveedor.',
            ]);
        }

        $result = $electronicBilling->issueOrQueue($document, $payload);

        if ((bool) ($result['queued'] ?? false)) {
            return back()->with('warning', 'Re-declaración encolada ('.$result['connection'].'/'.$result['queue'].').');
        }

        if (! (bool) ($result['ok'] ?? false)) {
            return back()->with('warning', 'Re-declaración enviada con error: '.($result['message'] ?? 'Error no especificado.'));
        }

        return back()->with('success', 'Re-declaración enviada correctamente al proveedor configurado.');
    }

    public function downloadXml(BillingDocument $document, SecurityScopeService $scopeService)
    {
        abort_unless($scopeService->canAccessBillingDocument(request()->user(), $document, 'billing'), 403);

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

    public function downloadCdr(BillingDocument $document, SecurityScopeService $scopeService)
    {
        abort_unless($scopeService->canAccessBillingDocument(request()->user(), $document, 'billing'), 403);

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

    public function downloadPdf(BillingDocument $document, InvoicePdfService $invoicePdfService, SecurityScopeService $scopeService)
    {
        abort_unless($scopeService->canAccessBillingDocument(request()->user(), $document, 'billing'), 403);

        $xmlPath = $this->resolveXmlPathFromDocument($document);
        if (! $xmlPath) {
            abort(404, 'XML no disponible para generar PDF.');
        }

        try {
            $pdfPath = $invoicePdfService->generateFromXml($xmlPath);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }
        if (! Storage::disk('local')->exists($pdfPath)) {
            abort(404, 'No se pudo generar el PDF del comprobante.');
        }

        return response()->download(
            Storage::disk('local')->path($pdfPath),
            "{$document->series}-{$document->number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    private function resolveXmlPathFromDocument(BillingDocument $document): ?string
    {
        $file = $document->xmlFile();
        if ($file && $file->storage_path !== '') {
            return $file->storage_path;
        }

        if (is_string($document->xml_path) && trim($document->xml_path) !== '') {
            return $document->xml_path;
        }

        $requestPath = data_get($document->request_payload, 'xml_path');
        if (is_string($requestPath) && trim($requestPath) !== '') {
            return $requestPath;
        }

        return null;
    }

    private function loadDocumentContext(BillingDocument $document): BillingDocument
    {
        $document->load([
            'order.items.product',
            'files',
            'responseHistories' => fn ($query) => $query->latest('id'),
        ]);

        return $document;
    }
}

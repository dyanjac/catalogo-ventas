<?php

namespace Modules\ElectronicDocuments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Models\BillingDocument;
use Modules\ElectronicDocuments\Services\InvoicePdfService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvoicePdfController extends Controller
{
    public function generate(string $serieNumero, InvoicePdfService $pdfService)
    {
        $document = $this->findDocumentBySerieNumero($serieNumero);
        if (! $document) {
            throw new NotFoundHttpException('No se encontró el comprobante para generar PDF.');
        }

        $xmlPath = $this->resolveXmlPathFromDocument($document);
        if ($xmlPath === null) {
            throw new NotFoundHttpException('El comprobante no tiene XML disponible.');
        }

        $pdfPath = $pdfService->generateFromXml($xmlPath);
        if (! Storage::disk('local')->exists($pdfPath)) {
            throw new NotFoundHttpException('No se pudo generar el PDF del comprobante.');
        }

        return response()->download(
            Storage::disk('local')->path($pdfPath),
            $serieNumero.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function findDocumentBySerieNumero(string $serieNumero): ?BillingDocument
    {
        [$series, $number] = array_pad(explode('-', $serieNumero, 2), 2, null);
        if (! is_string($series) || ! is_string($number)) {
            return null;
        }

        $series = strtoupper(trim($series));
        $rawNumber = trim($number);
        $normalizedNumber = ltrim($rawNumber, '0');
        if ($normalizedNumber === '') {
            $normalizedNumber = '0';
        }

        return BillingDocument::query()
            ->where('series', $series)
            ->where(function ($query) use ($normalizedNumber, $rawNumber) {
                $query->where('number', $normalizedNumber)
                    ->orWhere('number', $rawNumber);
            })
            ->with('files')
            ->latest('id')
            ->first();
    }

    private function resolveXmlPathFromDocument(BillingDocument $document): ?string
    {
        $xmlFile = $document->xmlFile();
        if ($xmlFile && $xmlFile->storage_path !== '') {
            return $xmlFile->storage_path;
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
}

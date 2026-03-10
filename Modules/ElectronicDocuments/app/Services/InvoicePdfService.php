<?php

namespace Modules\ElectronicDocuments\Services;

use Barryvdh\Snappy\Facades\SnappyPdf;
use DOMDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\ElectronicDocuments\Models\DocumentTemplate;
use RuntimeException;
use XSLTProcessor;

class InvoicePdfService
{
    public function generateFromXml(string $xmlPath, ?int $companyId = null): string
    {
        $this->assertDependencies();

        $xmlAbsolutePath = $this->resolveXmlAbsolutePath($xmlPath);
        if (! File::exists($xmlAbsolutePath)) {
            throw new RuntimeException('No se encontró el XML en la ruta: '.$xmlPath);
        }

        $xmlDom = new DOMDocument();
        $xmlContent = (string) File::get($xmlAbsolutePath);
        if (! @$xmlDom->loadXML($xmlContent)) {
            throw new RuntimeException('El XML no tiene un formato válido para generar PDF.');
        }

        $documentType = $this->detectDocumentType($xmlDom);
        $template = DocumentTemplate::activeForType($documentType, $companyId);
        if (! $template) {
            throw new RuntimeException('No existe plantilla activa para tipo de documento: '.$documentType);
        }

        $html = $this->transformXmlWithXslt($xmlDom, (string) $template->xslt_content);
        $pdf = SnappyPdf::loadHTML($html);
        $pdf->setTimeout(300);

        $pdfOutput = $pdf
            ->setOption('encoding', 'utf-8')
            ->setOption('enable-local-file-access', true)
            ->setPaper('a4')
            ->output();

        $serieNumero = $this->extractSerieNumero($xmlDom);
        $pdfPath = 'pdf/'.$serieNumero.'.pdf';
        Storage::disk('local')->put($pdfPath, $pdfOutput);

        return $pdfPath;
    }

    public function previewTemplateFromXml(string $xmlPath, string $xsltContent): string
    {
        $this->assertDependencies();

        $xmlAbsolutePath = $this->resolveXmlAbsolutePath($xmlPath);
        if (! File::exists($xmlAbsolutePath)) {
            throw new RuntimeException('No se encontró el XML para previsualización: '.$xmlPath);
        }

        $xmlDom = new DOMDocument();
        $xmlContent = (string) File::get($xmlAbsolutePath);
        if (! @$xmlDom->loadXML($xmlContent)) {
            throw new RuntimeException('El XML de previsualización no es válido.');
        }

        return $this->transformXmlWithXslt($xmlDom, $xsltContent);
    }

    public function detectDocumentType(DOMDocument $xmlDom): string
    {
        $root = $xmlDom->documentElement?->localName ?? '';
        $invoiceTypeCode = $this->xpathValue($xmlDom, '/*/cbc:InvoiceTypeCode');

        if ($root === 'Invoice') {
            if ($invoiceTypeCode === '01') {
                return 'factura';
            }
            if ($invoiceTypeCode === '03') {
                return 'boleta';
            }
        }

        if ($root === 'CreditNote') {
            return 'nota_credito';
        }

        if ($root === 'DebitNote') {
            return 'nota_debito';
        }

        if ($root === 'Retention') {
            return 'retencion';
        }

        if ($root === 'VoidedDocuments' || $root === 'SummaryDocuments') {
            return 'boleta';
        }

        return 'factura';
    }

    private function transformXmlWithXslt(DOMDocument $xmlDom, string $xsltContent): string
    {
        $xslDom = new DOMDocument();
        if (! @$xslDom->loadXML($xsltContent)) {
            throw new RuntimeException('La plantilla XSLT no tiene un formato XML válido.');
        }

        $processor = new XSLTProcessor();
        $processor->importStyleSheet($xslDom);
        $html = $processor->transformToXML($xmlDom);

        if (! is_string($html) || trim($html) === '') {
            throw new RuntimeException('No se pudo transformar el XML con la plantilla XSLT.');
        }

        return $html;
    }

    private function resolveXmlAbsolutePath(string $xmlPath): string
    {
        $path = trim($xmlPath);
        if ($path === '') {
            return '';
        }

        if (File::exists($path)) {
            return $path;
        }

        $candidates = [
            storage_path('app/'.$path),
            storage_path('app/public/'.$path),
            base_path($path),
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return $path;
    }

    private function extractSerieNumero(DOMDocument $xmlDom): string
    {
        $id = $this->xpathValue($xmlDom, '/*/cbc:ID');
        if ($id !== '') {
            return preg_replace('/[^A-Za-z0-9\-]/', '', $id) ?: 'documento';
        }

        return 'documento-'.now()->format('YmdHis');
    }

    private function xpathValue(DOMDocument $xmlDom, string $query): string
    {
        $xpath = new \DOMXPath($xmlDom);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $value = $xpath->evaluate('string('.$query.')');

        return is_string($value) ? trim($value) : '';
    }

    private function assertDependencies(): void
    {
        if (! class_exists(XSLTProcessor::class)) {
            throw new RuntimeException('Falta la extensión PHP xsl (XSLTProcessor). Instala php-xml/xsl en tu entorno.');
        }

        if (! class_exists(\Knp\Snappy\Pdf::class)) {
            throw new RuntimeException('Snappy no está disponible. Instala barryvdh/laravel-snappy.');
        }
    }
}

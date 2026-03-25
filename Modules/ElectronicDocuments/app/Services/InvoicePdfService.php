<?php

namespace Modules\ElectronicDocuments\Services;

use App\Services\OrganizationContextService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use DOMDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\AdminTheme\Services\AdminThemePaletteService;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Commerce\Services\CommerceSettingsService;
use Modules\ElectronicDocuments\Models\DocumentTemplate;
use RuntimeException;
use Throwable;
use XSLTProcessor;

class InvoicePdfService
{
    public function __construct(private readonly OrganizationContextService $organizationContext)
    {
    }

    public function generateFromXml(string $xmlPath, ?int $organizationId = null): string
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
        $template = DocumentTemplate::activeForType($documentType, $organizationId ?? $this->organizationContext->currentOrganizationId());
        if (! $template) {
            throw new RuntimeException('No existe plantilla activa para tipo de documento: '.$documentType);
        }

        $html = $this->transformXmlWithXslt(
            $xmlDom,
            (string) $template->xslt_content,
            $this->resolveTemplateParameters()
        );
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

        return $this->transformXmlWithXslt($xmlDom, $xsltContent, $this->resolveTemplateParameters());
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

    /**
     * @param array<string,string> $parameters
     */
    private function transformXmlWithXslt(DOMDocument $xmlDom, string $xsltContent, array $parameters = []): string
    {
        $xslDom = new DOMDocument();
        if (! @$xslDom->loadXML($xsltContent)) {
            throw new RuntimeException('La plantilla XSLT no tiene un formato XML válido.');
        }

        $processor = new XSLTProcessor();
        $processor->importStyleSheet($xslDom);

        foreach ($parameters as $key => $value) {
            $processor->setParameter('', $key, $value);
        }

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

    /**
     * @return array<string,string>
     */
    private function resolveTemplateParameters(): array
    {
        $commerce = app(CommerceSettingsService::class)->getForView();

        $params = [
            'company_name' => (string) ($commerce['name'] ?? ''),
            'company_tax_id' => (string) ($commerce['tax_id'] ?? ''),
            'company_address' => (string) ($commerce['address'] ?? ''),
            'company_phone' => (string) ($commerce['phone'] ?? ''),
            'company_mobile' => (string) ($commerce['mobile'] ?? ''),
            'company_email' => (string) ($commerce['email'] ?? ''),
            'company_logo_url' => (string) ($commerce['logo_url'] ?? ''),
            'company_logo_data_uri' => '',
            'company_logo_file_uri' => '',
        ];

        $params = array_merge($params, $this->resolvePaletteParameters());

        if (! Schema::hasTable('commerce_settings')) {
            return $params;
        }

        $setting = $this->currentCommerceSetting();
        $logoPath = trim((string) ($setting?->logo_path ?? ''));
        if ($logoPath === '' || ! Storage::disk('public')->exists($logoPath)) {
            return $params;
        }

        $absolutePath = Storage::disk('public')->path($logoPath);
        $mimeType = File::mimeType($absolutePath) ?: 'image/png';
        $content = File::get($absolutePath);

        $params['company_logo_url'] = asset('storage/'.$logoPath);
        $params['company_logo_data_uri'] = 'data:'.$mimeType.';base64,'.base64_encode($content);
        $params['company_logo_file_uri'] = 'file:///'.str_replace('\\', '/', $absolutePath);

        return $params;
    }

    private function currentCommerceSetting(): ?CommerceSetting
    {
        if (! Schema::hasColumn('commerce_settings', 'organization_id')) {
            return CommerceSetting::query()->first();
        }

        $organizationId = $this->organizationContext->currentOrganizationId();

        if ($organizationId) {
            return CommerceSetting::query()->where('organization_id', $organizationId)->first()
                ?? CommerceSetting::query()->whereNull('organization_id')->first()
                ?? CommerceSetting::query()->first();
        }

        return CommerceSetting::query()->whereNull('organization_id')->first()
            ?? CommerceSetting::query()->first();
    }

    /**
     * @return array<string,string>
     */
    private function resolvePaletteParameters(): array
    {
        $defaults = (array) config('admintheme.defaults', []);
        $palette = $defaults;

        if (class_exists(AdminThemePaletteService::class)) {
            try {
                $resolved = app(AdminThemePaletteService::class)->getPalette();
                if (is_array($resolved)) {
                    $palette = array_merge($defaults, $resolved);
                }
            } catch (Throwable) {
                $palette = $defaults;
            }
        }

        $params = [];
        foreach ($palette as $key => $value) {
            $params['palette_'.$key] = (string) $value;
        }

        $params['palette_primary'] = (string) ($palette['primary_button'] ?? '#000000');
        $params['palette_primary_hover'] = (string) ($palette['primary_button_hover'] ?? $params['palette_primary']);
        $params['palette_text'] = (string) ($palette['topbar_text'] ?? '#1f2d3d');
        $params['palette_border'] = (string) ($palette['card_border'] ?? '#dddddd');

        return $params;
    }
}

<?php

namespace Modules\Billing\Services\Providers;

use DateTime;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Models\BillingSetting;
use Throwable;

class GreenterBillingProvider extends AbstractBillingProvider
{
    public function code(): string
    {
        return 'greenter';
    }

    public function testConnection(BillingSetting $setting): array
    {
        $credentials = $this->resolvedCredentials($setting);
        $missing = [];

        foreach (['ruc', 'sol_user', 'sol_password', 'certificate_path', 'certificate_password'] as $key) {
            $value = $credentials[$key] ?? null;
            if (! is_string($value) || trim($value) === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            return [
                'ok' => false,
                'message' => 'Faltan credenciales: '.implode(', ', $missing),
            ];
        }

        $certAbsolutePath = $this->absoluteCertificatePath((string) $credentials['certificate_path']);
        if (! File::exists($certAbsolutePath)) {
            return [
                'ok' => false,
                'message' => 'No se encontró el certificado en la ruta: '.$certAbsolutePath,
            ];
        }

        if (! class_exists(\Greenter\Ws\Services\BillSender::class)) {
            return [
                'ok' => false,
                'message' => 'Greenter no está instalado. Ejecuta: composer require greenter/greenter',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Greenter configurado correctamente para '.$setting->environment.'.',
            'credentials_source' => $this->credentialSource($setting),
            'certificate_path' => $certAbsolutePath,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function issueDocument(BillingSetting $setting, array $payload): array
    {
        $test = $this->testConnection($setting);
        if (! $test['ok']) {
            return $test;
        }

        try {
            $primaryUblVersion = $this->resolveUblVersion($setting);
            $response = $this->issueWithUblVersion($setting, $payload, $test, $primaryUblVersion);
            $response['ubl_version'] = $primaryUblVersion;

            return $response;
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Error al emitir con Greenter: '.$e->getMessage(),
                'provider' => $this->code(),
                'environment' => $setting->environment,
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function resolvedCredentials(BillingSetting $setting): array
    {
        $credentials = (array) ($setting->provider_credentials['greenter'] ?? []);
        $defaultPath = trim((string) env('GREENTER_DEFAULT_CERT_PATH', 'storage/app/certificados/greenter-test-bundle.pem'));
        $defaultPassword = trim((string) env('GREENTER_DEFAULT_CERT_PASSWORD', 'MaestroTest2026!'));

        if (($credentials['certificate_path'] ?? '') === '' && $defaultPath !== '') {
            $credentials['certificate_path'] = $defaultPath;
        }

        if (($credentials['certificate_password'] ?? '') === '' && $defaultPassword !== '') {
            $credentials['certificate_password'] = $defaultPassword;
        }

        return $credentials;
    }

    private function credentialSource(BillingSetting $setting): string
    {
        $raw = (array) ($setting->provider_credentials['greenter'] ?? []);
        $hasCertPath = is_string($raw['certificate_path'] ?? null) && trim((string) $raw['certificate_path']) !== '';
        $hasCertPass = is_string($raw['certificate_password'] ?? null) && trim((string) $raw['certificate_password']) !== '';

        return ($hasCertPath && $hasCertPass) ? 'billing_settings' : 'fallback_default';
    }

    private function absoluteCertificatePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function buildSeeAndInvoice(BillingSetting $setting, array $payload, string $ublVersion): array
    {
        if (! class_exists(\Greenter\See::class)) {
            throw new \RuntimeException('Greenter no está instalado.');
        }

        $credentials = $this->resolvedCredentials($setting);
        $see = new \Greenter\See();
        $see->setService(
            $setting->environment === 'production'
                ? \Greenter\Ws\Services\SunatEndpoints::FE_PRODUCCION
                : \Greenter\Ws\Services\SunatEndpoints::FE_BETA
        );
        $see->setClaveSOL(
            (string) ($credentials['ruc'] ?? ''),
            (string) ($credentials['sol_user'] ?? ''),
            (string) ($credentials['sol_password'] ?? '')
        );

        $certPath = $this->absoluteCertificatePath((string) $credentials['certificate_path']);
        $certificate = $this->resolveCertificateForSigning(
            $certPath,
            (string) ($credentials['certificate_password'] ?? '')
        );
        $see->setCertificate($certificate);

        $invoice = $this->buildInvoiceFromPayload($setting, $payload, $ublVersion);

        return [$see, $invoice];
    }

    private function storeSignedXml(\Greenter\See $see, \Greenter\Model\Sale\Invoice $invoice): string
    {
        $xml = $see->getXmlSigned($invoice);

        if (! is_string($xml) || trim($xml) === '') {
            throw new \RuntimeException('Greenter no devolvió XML firmado.');
        }

        $dir = 'billing/xml/' . now()->format('Ym');
        $path = $dir . '/' . $invoice->getName() . '.xml';
        Storage::disk('public')->put($path, $xml);

        return $path;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function buildInvoiceFromPayload(BillingSetting $setting, array $payload, string $ublVersion): \Greenter\Model\Sale\Invoice
    {
        $documentType = $this->mapSaleDocumentType((string) ($payload['document_type'] ?? 'boleta'));
        $currency = strtoupper((string) ($payload['currency'] ?? 'PEN'));
        $issueDate = (string) ($payload['issue_date'] ?? now()->toDateString());

        $totals = is_array($payload['totals'] ?? null) ? $payload['totals'] : [];
        $subtotal = round((float) ($totals['subtotal'] ?? 0), 2);
        $tax = round((float) ($totals['tax'] ?? 0), 2);
        $total = round((float) ($totals['total'] ?? ($subtotal + $tax)), 2);
        $taxRate = $this->resolveIgvRate($payload, $subtotal, $tax);
        $taxPercent = round($taxRate * 100, 2);

        $company = $this->buildCompany($setting);
        $client = $this->buildClientFromPayload($payload);
        $details = $this->buildDetailsFromPayload($payload, $taxRate, $taxPercent);

        $invoice = new \Greenter\Model\Sale\Invoice();
        $invoice
            ->setUblVersion($ublVersion)
            ->setTipoOperacion('0101')
            ->setTipoDoc($documentType)
            ->setSerie((string) ($payload['series'] ?? 'F001'))
            ->setCorrelativo($this->normalizeCorrelative((string) ($payload['number'] ?? '1')))
            ->setFechaEmision(new DateTime($issueDate))
            ->setTipoMoneda($currency)
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($subtotal)
            ->setMtoOperExoneradas(0.00)
            ->setMtoOperInafectas(0.00)
            ->setMtoIGV($tax)
            ->setTotalImpuestos($tax)
            ->setValorVenta($subtotal)
            ->setSubTotal($total)
            ->setMtoImpVenta($total)
            ->setDetails($details);

        return $invoice;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $test
     * @return array<string,mixed>
     */
    private function issueWithUblVersion(
        BillingSetting $setting,
        array $payload,
        array $test,
        string $ublVersion
    ): array {
        [$see, $invoice] = $this->buildSeeAndInvoice($setting, $payload, $ublVersion);
        $xmlPath = $this->storeSignedXml($see, $invoice);
        $xmlContent = Storage::disk('public')->get($xmlPath);

        $sendResult = $see->send($invoice);
        if (! $sendResult) {
            return [
                'ok' => false,
                'message' => 'Greenter no devolvió respuesta al enviar el comprobante.',
                'provider' => $this->code(),
                'environment' => $setting->environment,
                'xml_path' => $xmlPath,
                'xml_hash' => hash('sha256', $xmlContent),
                'xml_source' => 'greenter-signed',
                'credentials_source' => $test['credentials_source'] ?? null,
                'certificate_path' => $test['certificate_path'] ?? null,
            ];
        }

        $ok = (bool) $sendResult->isSuccess();
        $error = $sendResult->getError();
        $message = $ok
            ? 'Comprobante enviado correctamente a SUNAT mediante Greenter.'
            : (string) ($error?->getMessage() ?: 'SUNAT/OSE devolvió error de emisión.');
        $statusCode = $error?->getCode();

        $response = [
            'ok' => $ok,
            'message' => $message,
            'provider' => $this->code(),
            'environment' => $setting->environment,
            'status_code' => is_string($statusCode) && is_numeric($statusCode) ? (int) $statusCode : null,
            'xml_path' => $xmlPath,
            'xml_hash' => hash('sha256', $xmlContent),
            'xml_source' => 'greenter-signed',
            'credentials_source' => $test['credentials_source'] ?? null,
            'certificate_path' => $test['certificate_path'] ?? null,
        ];

        if ($sendResult instanceof \Greenter\Model\Response\BillResult) {
            $cdrZip = $sendResult->getCdrZip();
            $cdrResponse = $sendResult->getCdrResponse();

            if (is_string($cdrZip) && $cdrZip !== '') {
                $response['cdr_base64'] = base64_encode($cdrZip);
            }

            if ($cdrResponse) {
                $response['sunat_cdr_code'] = $cdrResponse->getCode();
                $response['sunat_cdr_description'] = $cdrResponse->getDescription();
                $response['sunat_cdr_reference'] = $cdrResponse->getReference();
                $response['sunat_cdr_notes'] = $cdrResponse->getNotes();
            }
        }

        return $response;
    }

    private function buildCompany(BillingSetting $setting): \Greenter\Model\Company\Company
    {
        $credentials = $this->resolvedCredentials($setting);
        $raw = (array) ($setting->provider_credentials['greenter'] ?? []);

        $address = new \Greenter\Model\Company\Address();
        $address
            ->setUbigueo((string) ($raw['company_ubigeo'] ?? '150101'))
            ->setDepartamento((string) ($raw['company_department'] ?? 'LIMA'))
            ->setProvincia((string) ($raw['company_province'] ?? 'LIMA'))
            ->setDistrito((string) ($raw['company_district'] ?? 'LIMA'))
            ->setUrbanizacion((string) ($raw['company_urbanization'] ?? '-'))
            ->setDireccion((string) ($raw['company_address'] ?? 'DIRECCION NO CONFIGURADA'))
            ->setCodigoPais('PE')
            ->setCodLocal((string) ($raw['company_local_code'] ?? '0000'));

        $company = new \Greenter\Model\Company\Company();
        $company
            ->setRuc((string) ($credentials['ruc'] ?? ''))
            ->setRazonSocial((string) ($raw['company_business_name'] ?? 'EMPRESA NO CONFIGURADA S.A.C.'))
            ->setNombreComercial((string) ($raw['company_trade_name'] ?? $raw['company_business_name'] ?? 'EMPRESA NO CONFIGURADA'))
            ->setAddress($address);

        return $company;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function buildClientFromPayload(array $payload): \Greenter\Model\Client\Client
    {
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $address = new \Greenter\Model\Company\Address();
        $address
            ->setDireccion((string) ($customer['address'] ?? '-'))
            ->setDistrito((string) ($customer['city'] ?? 'LIMA'))
            ->setProvincia((string) ($customer['city'] ?? 'LIMA'))
            ->setDepartamento((string) ($customer['city'] ?? 'LIMA'))
            ->setCodigoPais('PE');

        $docType = $this->mapClientDocumentType((string) ($customer['document_type'] ?? ''));
        $docNumber = trim((string) ($customer['document_number'] ?? ''));
        if ($docNumber === '') {
            $docNumber = $docType === '6' ? '00000000000' : '00000000';
        }

        $client = new \Greenter\Model\Client\Client();
        $client
            ->setTipoDoc($docType)
            ->setNumDoc($docNumber)
            ->setRznSocial((string) ($customer['name'] ?? 'CLIENTE VARIOS'))
            ->setAddress($address);

        return $client;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<int,\Greenter\Model\Sale\SaleDetail>
     */
    private function buildDetailsFromPayload(array $payload, float $taxRate, float $taxPercent): array
    {
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        return collect($items)
            ->map(function ($row) use ($taxRate, $taxPercent) {
                $line = is_array($row) ? $row : [];
                $quantity = max(1, (float) ($line['quantity'] ?? 1));
                $unitValue = round((float) ($line['unit_price'] ?? 0), 6);
                $lineBase = isset($line['line_subtotal'])
                    ? round((float) $line['line_subtotal'], 2)
                    : round($unitValue * $quantity, 2);
                $igv = round($lineBase * $taxRate, 2);
                $lineTotal = round($lineBase + $igv, 2);
                $unitPrice = $quantity > 0 ? round($lineTotal / $quantity, 6) : round($unitValue * (1 + $taxRate), 6);

                $detail = new \Greenter\Model\Sale\SaleDetail();
                $detail
                    ->setCodProducto((string) ($line['sku'] ?? ('ITEM-' . ($line['product_id'] ?? '0'))))
                    ->setUnidad('NIU')
                    ->setCantidad($quantity)
                    ->setDescripcion((string) ($line['name'] ?? 'ITEM'))
                    ->setMtoValorUnitario($unitValue)
                    ->setMtoPrecioUnitario($unitPrice)
                    ->setMtoBaseIgv($lineBase)
                    ->setPorcentajeIgv($taxPercent)
                    ->setIgv($igv)
                    ->setTipAfeIgv('10')
                    ->setTotalImpuestos($igv)
                    ->setMtoValorVenta($lineBase);

                return $detail;
            })
            ->values()
            ->all();
    }

    private function mapSaleDocumentType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'factura', '01' => '01',
            'boleta', '03' => '03',
            default => '03',
        };
    }

    private function mapClientDocumentType(string $type): string
    {
        return match (strtoupper(trim($type))) {
            'RUC', '6' => '6',
            'DNI', '1' => '1',
            'CE', '4' => '4',
            'PAS', 'PASAPORTE', '7' => '7',
            default => '0',
        };
    }

    private function normalizeCorrelative(string $number): string
    {
        $trimmed = ltrim(trim($number), '0');
        return $trimmed !== '' ? $trimmed : '1';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function resolveIgvRate(array $payload, float $subtotal, float $tax): float
    {
        $explicit = $payload['tax_rate'] ?? null;
        if (is_numeric($explicit)) {
            $value = (float) $explicit;
            if ($value > 1) {
                $value = $value / 100;
            }

            return round($value, 4);
        }

        if ($subtotal <= 0 || $tax <= 0) {
            return 0.18;
        }

        $computed = $tax / $subtotal;
        // Evita rechazos SUNAT por tasas no exactas derivadas de redondeos (ej: 17.988%)
        if (abs($computed - 0.18) <= 0.01) {
            return 0.18;
        }

        return round($computed, 4);
    }

    private function resolveUblVersion(BillingSetting $setting): string
    {
        $raw = (array) ($setting->provider_credentials['greenter'] ?? []);
        $defaultVersion = $setting->environment === 'sandbox' ? '2.0' : '2.1';
        $value = trim((string) ($raw['ubl_version'] ?? $defaultVersion));

        // En sandbox SUNAT beta suele validar catálogo de transacción bajo esquema UBL 2.0.
        if ($setting->environment === 'sandbox' && $value === '2.1') {
            return '2.0';
        }

        return in_array($value, ['2.0', '2.1'], true) ? $value : $defaultVersion;
    }

    private function resolveCertificateForSigning(string $absolutePath, string $password): string
    {
        $content = (string) File::get($absolutePath);
        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['pfx', 'p12'], true)) {
            $certs = [];
            if (! openssl_pkcs12_read($content, $certs, $password)) {
                throw new \RuntimeException('No se pudo abrir el certificado PFX/P12 con la clave indicada.');
            }

            $public = (string) ($certs['cert'] ?? '');
            $private = (string) ($certs['pkey'] ?? '');
            if (trim($public) === '' || trim($private) === '') {
                throw new \RuntimeException('El archivo PFX/P12 no contiene certificado público y clave privada válidos.');
            }

            return trim($public) . PHP_EOL . trim($private) . PHP_EOL;
        }

        if (! str_contains($content, 'BEGIN CERTIFICATE')) {
            throw new \RuntimeException('El certificado PEM no contiene bloque BEGIN CERTIFICATE.');
        }

        $privateKey = @openssl_pkey_get_private($content, $password);
        if (! $privateKey) {
            $privateKey = @openssl_pkey_get_private($content);
        }
        if (! $privateKey) {
            throw new \RuntimeException(
                'No se pudo leer la clave privada del PEM. Verifica que el archivo incluya PRIVATE KEY y su password correcta.'
            );
        }

        $privateKeyPem = '';
        if (! openssl_pkey_export($privateKey, $privateKeyPem, null)) {
            throw new \RuntimeException('No se pudo exportar la clave privada del certificado.');
        }

        $publicCert = $this->extractPemBlock($content, 'CERTIFICATE');
        if ($publicCert === null) {
            throw new \RuntimeException('No se pudo extraer el certificado público del archivo PEM.');
        }

        return trim($publicCert) . PHP_EOL . trim($privateKeyPem) . PHP_EOL;
    }

    private function extractPemBlock(string $content, string $type): ?string
    {
        $pattern = '/-----BEGIN ' . preg_quote($type, '/') . '-----.*?-----END ' . preg_quote($type, '/') . '-----/s';
        if (! preg_match($pattern, $content, $matches)) {
            return null;
        }

        return $matches[0];
    }
}

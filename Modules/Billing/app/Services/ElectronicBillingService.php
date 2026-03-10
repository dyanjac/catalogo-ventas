<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Models\BillingDocumentFile;
use Modules\Billing\Models\BillingSetting;
use Modules\Billing\Services\Xml\BillingXmlGenerator;

class ElectronicBillingService
{
    public function __construct(
        private readonly BillingProviderResolver $resolver,
        private readonly BillingXmlGenerator $xmlGenerator
    )
    {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function issue(BillingDocument $document, array $payload): array
    {
        $setting = BillingSetting::query()->first();

        if (! $setting || ! $setting->enabled) {
            return [
                'ok' => false,
                'message' => 'La facturación electrónica está desactivada.',
            ];
        }

        $xmlPath = $this->xmlGenerator->generate($document, $payload);
        $xmlHash = hash('sha256', Storage::disk('public')->get($xmlPath));
        $xmlSize = Storage::disk('public')->size($xmlPath);

        $payload['xml_path'] = $xmlPath;
        $payload['xml_hash'] = $xmlHash;

        $this->storeFileRecord($document, 'xml', [
            'storage_disk' => 'public',
            'storage_path' => $xmlPath,
            'mime_type' => 'application/xml',
            'size' => $xmlSize,
            'hash_sha256' => $xmlHash,
            'metadata' => [
                'source' => 'xml-generator',
            ],
        ]);

        $provider = $this->resolver->resolveFromSetting($setting);
        $result = $provider->issueDocument($setting, $payload);

        $document->update([
            'provider' => $setting->provider,
            'request_payload' => $payload,
            'response_payload' => $result,
            'status' => (bool) ($result['ok'] ?? false) ? 'issued' : 'error',
            'issued_at' => (bool) ($result['ok'] ?? false) ? now() : null,
        ]);

        $this->persistCdrFromResult($document, $result);

        return $result;
    }

    /**
     * @param array<string,mixed> $result
     */
    private function persistCdrFromResult(BillingDocument $document, array $result): void
    {
        $path = data_get($result, 'cdr_path') ?? data_get($result, 'body.cdr_path');
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            $this->storeFileRecord($document, 'cdr', [
                'storage_disk' => 'public',
                'storage_path' => $path,
                'mime_type' => 'application/xml',
                'size' => Storage::disk('public')->size($path),
                'hash_sha256' => hash('sha256', Storage::disk('public')->get($path)),
                'metadata' => ['source' => 'provider-path'],
            ]);

            return;
        }

        $base64 = data_get($result, 'cdr_base64')
            ?? data_get($result, 'body.cdr_base64')
            ?? data_get($result, 'body.cdrZipBase64');

        if (is_string($base64) && $base64 !== '') {
            $decoded = base64_decode($base64, true);
            if ($decoded !== false) {
                $dir = 'billing/cdr/' . now()->format('Ym');
                $path = $dir . '/R-' . $document->series . '-' . $document->number . '.zip';
                Storage::disk('public')->put($path, $decoded);

                $this->storeFileRecord($document, 'cdr', [
                    'storage_disk' => 'public',
                    'storage_path' => $path,
                    'mime_type' => 'application/zip',
                    'size' => Storage::disk('public')->size($path),
                    'hash_sha256' => hash('sha256', $decoded),
                    'metadata' => ['source' => 'provider-base64'],
                ]);

                return;
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function storeFileRecord(BillingDocument $document, string $fileType, array $data): void
    {
        BillingDocumentFile::query()->updateOrCreate(
            [
                'billing_document_id' => $document->id,
                'file_type' => $fileType,
            ],
            [
                'storage_disk' => (string) ($data['storage_disk'] ?? 'public'),
                'storage_path' => (string) ($data['storage_path'] ?? ''),
                'mime_type' => $data['mime_type'] ?? null,
                'size' => $data['size'] ?? null,
                'hash_sha256' => $data['hash_sha256'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]
        );
    }
}

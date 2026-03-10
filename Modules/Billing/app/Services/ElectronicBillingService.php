<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Billing\Models\BillingDocument;
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

        $payload['xml_path'] = $xmlPath;
        $payload['xml_hash'] = $xmlHash;

        $provider = $this->resolver->resolveFromSetting($setting);
        $result = $provider->issueDocument($setting, $payload);

        $document->update([
            'provider' => $setting->provider,
            'request_payload' => $payload,
            'response_payload' => $result,
            'xml_path' => $xmlPath,
            'xml_hash' => $xmlHash,
            'status' => (bool) ($result['ok'] ?? false) ? 'issued' : 'error',
            'issued_at' => (bool) ($result['ok'] ?? false) ? now() : null,
        ]);

        return $result;
    }
}

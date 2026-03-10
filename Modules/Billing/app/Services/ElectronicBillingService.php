<?php

namespace Modules\Billing\Services;

use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Models\BillingSetting;

class ElectronicBillingService
{
    public function __construct(private readonly BillingProviderResolver $resolver)
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

        $provider = $this->resolver->resolveFromSetting($setting);
        $result = $provider->issueDocument($setting, $payload);

        $document->update([
            'provider' => $setting->provider,
            'request_payload' => $payload,
            'response_payload' => $result,
            'status' => (bool) ($result['ok'] ?? false) ? 'issued' : 'error',
            'issued_at' => (bool) ($result['ok'] ?? false) ? now() : null,
        ]);

        return $result;
    }
}

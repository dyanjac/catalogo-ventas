<?php

namespace Modules\Billing\Services\Providers;

use Modules\Billing\Models\BillingSetting;

class NubefactBillingProvider extends AbstractBillingProvider
{
    public function code(): string
    {
        return 'nubefact';
    }

    public function testConnection(BillingSetting $setting): array
    {
        $validation = $this->validateRequiredCredentials($setting, [
            'api_url',
            'api_token',
        ]);

        if (! $validation['ok']) {
            return $validation;
        }

        return [
            'ok' => true,
            'message' => 'Credenciales NubeFact registradas correctamente.',
        ];
    }
}

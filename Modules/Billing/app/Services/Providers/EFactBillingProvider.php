<?php

namespace Modules\Billing\Services\Providers;

use Modules\Billing\Models\BillingSetting;

class EFactBillingProvider extends AbstractBillingProvider
{
    public function code(): string
    {
        return 'efact';
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
            'message' => 'Credenciales eFact registradas correctamente.',
        ];
    }
}

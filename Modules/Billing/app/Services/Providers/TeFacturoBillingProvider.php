<?php

namespace Modules\Billing\Services\Providers;

use Modules\Billing\Models\BillingSetting;

class TeFacturoBillingProvider extends AbstractBillingProvider
{
    public function code(): string
    {
        return 'tefacturo';
    }

    public function testConnection(BillingSetting $setting): array
    {
        $validation = $this->validateRequiredCredentials($setting, [
            'api_url',
            'api_user',
            'api_password',
        ]);

        if (! $validation['ok']) {
            return $validation;
        }

        return [
            'ok' => true,
            'message' => 'Credenciales TeFacturo registradas correctamente.',
        ];
    }
}

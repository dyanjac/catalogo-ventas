<?php

namespace Modules\Billing\Services\Providers;

use Modules\Billing\Models\BillingSetting;

class GreenterBillingProvider extends AbstractBillingProvider
{
    public function code(): string
    {
        return 'greenter';
    }

    public function testConnection(BillingSetting $setting): array
    {
        $validation = $this->validateRequiredCredentials($setting, [
            'ruc',
            'sol_user',
            'sol_password',
            'certificate_path',
            'certificate_password',
        ]);

        if (! $validation['ok']) {
            return $validation;
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
        ];
    }
}

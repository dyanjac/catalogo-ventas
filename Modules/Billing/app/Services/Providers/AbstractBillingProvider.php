<?php

namespace Modules\Billing\Services\Providers;

use Modules\Billing\Models\BillingSetting;
use Modules\Billing\Services\Contracts\BillingProviderInterface;

abstract class AbstractBillingProvider implements BillingProviderInterface
{
    /**
     * @param array<string> $requiredKeys
     * @return array{ok:bool,message:string}
     */
    protected function validateRequiredCredentials(BillingSetting $setting, array $requiredKeys): array
    {
        $credentials = $setting->provider_credentials[$this->code()] ?? [];
        $missing = [];

        foreach ($requiredKeys as $key) {
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

        return [
            'ok' => true,
            'message' => 'Configuración válida.',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function issueDocument(BillingSetting $setting, array $payload): array
    {
        return [
            'ok' => false,
            'message' => 'Emisión aún no implementada para el proveedor '.$this->code().'.',
            'provider' => $this->code(),
            'payload' => $payload,
        ];
    }
}

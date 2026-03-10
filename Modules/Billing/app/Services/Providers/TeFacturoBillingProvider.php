<?php

namespace Modules\Billing\Services\Providers;

use Illuminate\Support\Facades\Http;
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

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function issueDocument(BillingSetting $setting, array $payload): array
    {
        $validation = $this->testConnection($setting);
        if (! $validation['ok']) {
            return $validation;
        }

        $credentials = $setting->provider_credentials['tefacturo'] ?? [];
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withBasicAuth((string) ($credentials['api_user'] ?? ''), (string) ($credentials['api_password'] ?? ''))
                ->post((string) ($credentials['api_url'] ?? ''), $payload);

            return [
                'ok' => $response->successful(),
                'message' => $response->successful() ? 'Documento enviado a TeFacturo.' : 'Error al enviar a TeFacturo.',
                'status_code' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Error de conexión con TeFacturo: '.$e->getMessage(),
            ];
        }
    }
}

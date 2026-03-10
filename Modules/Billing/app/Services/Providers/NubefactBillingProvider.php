<?php

namespace Modules\Billing\Services\Providers;

use Illuminate\Support\Facades\Http;
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

        $credentials = $setting->provider_credentials['nubefact'] ?? [];
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withToken((string) ($credentials['api_token'] ?? ''))
                ->post((string) ($credentials['api_url'] ?? ''), $payload);

            return [
                'ok' => $response->successful(),
                'message' => $response->successful() ? 'Documento enviado a NubeFact.' : 'Error al enviar a NubeFact.',
                'status_code' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Error de conexión con NubeFact: '.$e->getMessage(),
            ];
        }
    }
}

<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\Http;

class CustomerDocumentLookupService
{
    /**
     * @return array{ok:bool,message:string,name:?string,address:?string,city:?string,phone:?string,raw:array}
     */
    public function lookup(string $documentType, string $documentNumber): array
    {
        if (! config('sales.document_lookup.enabled', true)) {
            return $this->error('La consulta externa de documentos está desactivada.');
        }

        $normalizedType = strtoupper(trim($documentType));
        $normalizedNumber = trim($documentNumber);

        if ($normalizedNumber === '') {
            return $this->error('Ingresa un número de documento para consultar.');
        }

        $configKey = match ($normalizedType) {
            'DNI' => 'dni',
            'RUC' => 'ruc',
            default => null,
        };

        if ($configKey === null) {
            return $this->error('Solo se admite consulta externa para DNI o RUC.');
        }

        $endpoint = (string) config("sales.document_lookup.{$configKey}.url", '');
        $payloadKey = (string) config("sales.document_lookup.{$configKey}.payload_key", '');

        if ($endpoint === '' || $payloadKey === '') {
            return $this->error("Configura el endpoint de consulta para {$normalizedType}.");
        }

        $timeout = (int) config('sales.document_lookup.timeout', 10);
        $verify = config('sales.document_lookup.verify', true);
        $caBundle = trim((string) config('sales.document_lookup.ca_bundle', ''));

        if ($caBundle !== '') {
            $verify = $caBundle;
        }

        try {
            $response = $this->performRequest($endpoint, $payloadKey, $normalizedNumber, $timeout, $verify);
        } catch (\Throwable $e) {
            if (
                $this->shouldRetryWithoutSslVerification($e->getMessage(), $verify)
            ) {
                try {
                    $response = $this->performRequest($endpoint, $payloadKey, $normalizedNumber, $timeout, false);
                } catch (\Throwable $retryException) {
                    return $this->error('No se pudo consultar el servicio externo: '.$retryException->getMessage());
                }
            } else {
            return $this->error('No se pudo consultar el servicio externo: '.$e->getMessage());
            }
        }

        if (! $response->successful()) {
            return $this->error('El servicio externo respondió con error HTTP '.$response->status().'.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            return $this->error('La respuesta del servicio externo no es válida.');
        }

        $success = (bool) data_get($body, 'Exito', false) || (bool) data_get($body, 'Contenido.Exito', false);
        $content = data_get($body, 'Contenido');

        if (! is_array($content)) {
            $content = [];
        }

        if (! $success && $content === []) {
            return $this->error((string) (data_get($body, 'Mensaje') ?: 'No se encontró información para el documento consultado.'), $body);
        }

        $name = $this->resolveName($content);
        $address = $this->resolveFirstString($content, ['direccion', 'direccionCompleta', 'domicilioLegal']);
        $city = $this->resolveFirstString($content, ['ciudad', 'provincia', 'ubigeo']);
        $phone = $this->resolveFirstString($content, ['telefono', 'celular', 'fax']);

        return [
            'ok' => true,
            'message' => 'Documento consultado correctamente.',
            'name' => $name,
            'address' => $address,
            'city' => $city,
            'phone' => $phone,
            'raw' => $body,
        ];
    }

    /**
     * @param array<string,mixed> $content
     */
    private function resolveName(array $content): ?string
    {
        $candidates = [
            data_get($content, 'nombrecompleto'),
            data_get($content, 'razonSocial'),
            data_get($content, 'nombre'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        $parts = array_filter([
            trim((string) data_get($content, 'prenombres')),
            trim((string) data_get($content, 'apPrimer')),
            trim((string) data_get($content, 'apSegundo')),
        ]);

        if ($parts === []) {
            return null;
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param array<string,mixed> $content
     * @param array<int,string> $keys
     */
    private function resolveFirstString(array $content, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) data_get($content, $key));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{ok:bool,message:string,name:?string,address:?string,city:?string,phone:?string,raw:array}
     */
    private function error(string $message, array $raw = []): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'name' => null,
            'address' => null,
            'city' => null,
            'phone' => null,
            'raw' => $raw,
        ];
    }

    private function performRequest(string $endpoint, string $payloadKey, string $documentNumber, int $timeout, mixed $verify)
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->withOptions(['verify' => $verify])
            ->post($endpoint, [
                $payloadKey => $documentNumber,
            ]);
    }

    private function shouldRetryWithoutSslVerification(string $message, mixed $verify): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        if ($verify === false) {
            return false;
        }

        return str_contains($message, 'cURL error 60');
    }
}

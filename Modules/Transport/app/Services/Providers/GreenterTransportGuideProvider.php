<?php

declare(strict_types=1);

namespace Modules\Transport\Services\Providers;

use Greenter\Api;
use Modules\Transport\Models\TransportGuide;
use Modules\Transport\Models\TransportSetting;
use Modules\Transport\Services\Contracts\TransportGuideProviderInterface;
use Modules\Transport\Services\GreenterDespatchFactory;
use Throwable;

class GreenterTransportGuideProvider implements TransportGuideProviderInterface
{
    private const AUTH_ENDPOINT = 'https://api-seguridad.sunat.gob.pe/v1';

    private const CPE_ENDPOINT = 'https://api-cpe.sunat.gob.pe/v1';

    public function __construct(private readonly GreenterDespatchFactory $factory) {}

    public function code(): string
    {
        return 'greenter';
    }

    public function validateCredentials(TransportSetting $setting): array
    {
        $credentials = $setting->provider_credentials ?? [];
        $required = ['company_ruc', 'company_legal_name', 'company_ubigeo', 'company_address', 'sol_user', 'sol_password', 'api_client_id', 'api_client_secret', 'certificate_path'];
        $missing = array_values(array_filter($required, fn (string $key): bool => trim((string) ($credentials[$key] ?? '')) === ''));
        if ($missing !== []) {
            return ['ok' => false, 'message' => 'Faltan credenciales GRE: '.implode(', ', $missing)];
        }
        try {
            $path = $this->certificatePath((string) $credentials['certificate_path']);
        } catch (\InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }
        if (! is_file($path) || ! is_readable($path)) {
            return ['ok' => false, 'message' => 'El certificado GRE no existe o no es legible.'];
        }
        $certificate = file_get_contents($path);
        if (! is_string($certificate) || ! str_contains($certificate, 'BEGIN')) {
            return ['ok' => false, 'message' => 'El certificado GRE debe estar en formato PEM.'];
        }

        return ['ok' => true, 'message' => 'Credenciales GRE estructuralmente validas.'];
    }

    public function submit(TransportSetting $setting, TransportGuide $guide): array
    {
        try {
            $api = $this->api($setting);
            $result = $api->send($this->factory->build($setting, $guide));
            $xml = $api->getLastXml();
            if (! $result?->isSuccess()) {
                return $this->errorResult($result?->getError(), $xml);
            }

            return [
                'ok' => true,
                'status' => 'submitted',
                'message' => 'GRE enviada a SUNAT y pendiente de consulta.',
                'ticket' => method_exists($result, 'getTicket') ? $result->getTicket() : null,
                'provider_code' => '98',
                'xml' => $xml,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'status' => 'uncertain', 'message' => 'El resultado del envio GRE es incierto y debe conciliarse antes de reenviar.', 'provider_code' => 'SUBMIT_EXCEPTION'];
        }
    }

    public function poll(TransportSetting $setting, TransportGuide $guide): array
    {
        try {
            $result = $this->api($setting)->getStatus($guide->provider_ticket);
            if ($result->getCode() === '98') {
                return ['ok' => true, 'status' => 'submitted', 'message' => 'SUNAT continua procesando la GRE.', 'provider_code' => '98'];
            }
            if (! $result->isSuccess()) {
                return $this->errorResult($result->getError(), null, $result->getCode() === '99' ? 'rejected' : 'error');
            }
            $cdr = $result->getCdrResponse();
            $notes = $cdr?->getNotes() ?? [];

            return [
                'ok' => true,
                'status' => $notes === [] ? 'accepted' : 'accepted_with_observation',
                'message' => $cdr?->getDescription() ?: 'GRE aceptada por SUNAT.',
                'provider_code' => $cdr?->getCode() ?: (string) $result->getCode(),
                'provider_description' => $cdr?->getDescription(),
                'provider_notes' => $notes,
                'cdr' => $result->getCdrZip(),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'status' => 'submitted', 'message' => 'No se pudo consultar SUNAT; la GRE conserva su ticket para reintentar la consulta.', 'provider_code' => 'QUERY_EXCEPTION'];
        }
    }

    private function api(TransportSetting $setting): Api
    {
        $credentials = $setting->provider_credentials ?? [];
        $endpoints = ['auth' => self::AUTH_ENDPOINT, 'cpe' => self::CPE_ENDPOINT];
        $certificate = file_get_contents($this->certificatePath((string) ($credentials['certificate_path'] ?? '')));
        if (! is_string($certificate)) {
            throw new \RuntimeException('No se pudo leer el certificado GRE.');
        }

        return (new Api($endpoints))
            ->setCertificate($certificate)
            ->setClaveSOL((string) $credentials['company_ruc'], (string) $credentials['sol_user'], (string) $credentials['sol_password'])
            ->setApiCredentials((string) $credentials['api_client_id'], (string) $credentials['api_client_secret']);
    }

    private function certificatePath(string $path): string
    {
        if (preg_match('/^[A-Za-z0-9._-]+\.pem$/', $path) !== 1) {
            throw new \InvalidArgumentException('El certificado debe ser un archivo PEM del directorio privado de certificados GRE.');
        }

        return storage_path('app/private/transport/certificates/'.$path);
    }

    /** @return array<string, mixed> */
    private function errorResult(mixed $error, ?string $xml, string $status = 'error'): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'message' => $error?->getMessage() ?? 'SUNAT rechazo la operacion GRE.',
            'provider_code' => $error?->getCode() ?? 'UNKNOWN',
            'xml' => $xml,
        ];
    }
}

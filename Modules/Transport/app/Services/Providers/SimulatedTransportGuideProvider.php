<?php

declare(strict_types=1);

namespace Modules\Transport\Services\Providers;

use Modules\Transport\Models\TransportGuide;
use Modules\Transport\Models\TransportSetting;
use Modules\Transport\Services\Contracts\TransportGuideProviderInterface;

class SimulatedTransportGuideProvider implements TransportGuideProviderInterface
{
    public function code(): string
    {
        return 'simulation';
    }

    public function validateCredentials(TransportSetting $setting): array
    {
        return ['ok' => true, 'message' => 'El modo simulacion no requiere credenciales externas.'];
    }

    public function submit(TransportSetting $setting, TransportGuide $guide): array
    {
        $ticket = 'SIM-'.strtoupper(substr(hash('sha256', $guide->organization_id.':'.$guide->id.':'.$guide->payload_hash), 0, 24));
        $xml = '<?xml version="1.0" encoding="UTF-8"?><TransportGuide simulation="true" type="'.e($guide->guide_type->value).'" number="'.e($guide->formattedNumber()).'" payloadHash="'.e((string) $guide->payload_hash).'" />';

        return [
            'ok' => true,
            'status' => 'submitted',
            'message' => 'GRE recibida por el proveedor simulado.',
            'ticket' => $ticket,
            'xml' => $xml,
            'provider_code' => '98',
        ];
    }

    public function poll(TransportSetting $setting, TransportGuide $guide): array
    {
        $cdr = 'SIMULATED-CDR|'.$guide->formattedNumber().'|ACCEPTED|'.$guide->payload_hash;

        return [
            'ok' => true,
            'status' => 'accepted',
            'message' => 'GRE aceptada por el proveedor simulado.',
            'ticket' => $guide->provider_ticket,
            'provider_code' => '0',
            'provider_description' => 'Aceptada (simulacion)',
            'cdr' => $cdr,
        ];
    }
}

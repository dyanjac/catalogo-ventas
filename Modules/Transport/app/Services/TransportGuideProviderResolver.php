<?php

declare(strict_types=1);

namespace Modules\Transport\Services;

use InvalidArgumentException;
use Modules\Transport\Services\Contracts\TransportGuideProviderInterface;
use Modules\Transport\Services\Providers\GreenterTransportGuideProvider;
use Modules\Transport\Services\Providers\SimulatedTransportGuideProvider;

class TransportGuideProviderResolver
{
    public function resolve(string $provider): TransportGuideProviderInterface
    {
        return match ($provider) {
            'simulation' => app(SimulatedTransportGuideProvider::class),
            'greenter' => app(GreenterTransportGuideProvider::class),
            default => throw new InvalidArgumentException("Proveedor GRE no soportado: {$provider}"),
        };
    }
}

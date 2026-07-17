<?php

declare(strict_types=1);

namespace Modules\Transport\Enums;

enum TransportGuideType: string
{
    case Sender = 'sender';
    case Carrier = 'carrier';

    public function sunatCode(): string
    {
        return $this === self::Sender ? '09' : '31';
    }

    public function seriesPrefix(): string
    {
        return $this === self::Sender ? 'T' : 'V';
    }
}

<?php

declare(strict_types=1);

namespace Modules\Transport\Enums;

enum TransportEnvironment: string
{
    case Simulation = 'simulation';
    case Production = 'production';
}

<?php

declare(strict_types=1);

namespace Modules\Transport\Enums;

enum TransportMode: string
{
    case Public = '01';
    case Private = '02';
}

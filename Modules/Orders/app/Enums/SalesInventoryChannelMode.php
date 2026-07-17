<?php

declare(strict_types=1);

namespace Modules\Orders\Enums;

enum SalesInventoryChannelMode: string
{
    case Legacy = 'legacy';
    case Shadow = 'shadow';
    case Active = 'active';
}

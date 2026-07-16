<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryLedgerRolloutMode: string
{
    case Off = 'off';
    case Shadow = 'shadow';
    case Active = 'active';
}

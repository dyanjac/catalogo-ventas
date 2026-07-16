<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryMovementType: string
{
    case OpeningStock = 'opening_stock';
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
}

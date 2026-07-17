<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryTransferEventType: string
{
    case Created = 'created';
    case Dispatched = 'dispatched';
    case Received = 'received';
    case Cancelled = 'cancelled';
}

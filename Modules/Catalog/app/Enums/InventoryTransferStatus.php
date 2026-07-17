<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryTransferStatus: string
{
    case Draft = 'draft';
    case InTransit = 'in_transit';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}

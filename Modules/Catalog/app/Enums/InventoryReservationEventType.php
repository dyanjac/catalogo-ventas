<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryReservationEventType: string
{
    case Reserved = 'reserved';
    case Released = 'released';
    case Expired = 'expired';
    case Consumed = 'consumed';
}

<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Expired = 'expired';
    case Consumed = 'consumed';
}

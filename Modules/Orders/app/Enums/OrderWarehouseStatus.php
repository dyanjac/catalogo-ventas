<?php

declare(strict_types=1);

namespace Modules\Orders\Enums;

enum OrderWarehouseStatus: string
{
    case LegacyCompleted = 'legacy_completed';
    case NotRequired = 'not_required';
    case Reserved = 'reserved';
    case DispatchRequested = 'dispatch_requested';
    case Dispatched = 'dispatched';
    case Released = 'released';
    case ReturnRequested = 'return_requested';
    case Returned = 'returned';
    case ReservationExpired = 'reservation_expired';
}

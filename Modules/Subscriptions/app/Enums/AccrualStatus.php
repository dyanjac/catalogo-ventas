<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Enums;

enum AccrualStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case EventRecorded = 'event_recorded';
    case Error = 'error';
    case Cancelled = 'cancelled';
}

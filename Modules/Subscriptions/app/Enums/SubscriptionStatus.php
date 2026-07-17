<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Ended = 'ended';
}

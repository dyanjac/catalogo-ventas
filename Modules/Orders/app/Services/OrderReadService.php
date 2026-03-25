<?php

namespace Modules\Orders\Services;

use Modules\Orders\Contracts\OrderReadServiceInterface;
use Modules\Orders\Entities\Order;

class OrderReadService implements OrderReadServiceInterface
{
    public function findById(int $orderId): ?Order
    {
        return Order::query()->forCurrentOrganization()->find($orderId);
    }

    public function findByIdWithItems(int $orderId): ?Order
    {
        return Order::query()->forCurrentOrganization()->with(['items.product'])->find($orderId);
    }
}

<?php

namespace Modules\Orders\Contracts;

use Modules\Orders\Entities\Order;

interface OrderReadServiceInterface
{
    public function findById(int $orderId): ?Order;

    public function findByIdWithItems(int $orderId): ?Order;
}


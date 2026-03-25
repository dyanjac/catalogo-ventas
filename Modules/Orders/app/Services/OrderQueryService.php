<?php

namespace Modules\Orders\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Orders\Entities\Order;
use Modules\Orders\Repositories\OrderRepositoryInterface;

class OrderQueryService
{
    public function __construct(private readonly OrderRepositoryInterface $orders)
    {
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function myOrders(int $userId, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->orders->paginateUserOrders($userId, $filters, $perPage);
    }

    public function myOrderDetailOrFail(int $userId, int $orderId): Order
    {
        $order = Order::query()
            ->forCurrentOrganization()
            ->with(['items.product'])
            ->findOrFail($orderId);

        abort_unless($order->user_id === $userId, 403);

        return $order;
    }
}

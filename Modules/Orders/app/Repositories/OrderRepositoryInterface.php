<?php

namespace Modules\Orders\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Orders\Entities\Order;

interface OrderRepositoryInterface
{
    public function nextOrderNumber(string $series): int;

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): Order;

    /**
     * @param array<string,mixed> $filters
     */
    public function paginateUserOrders(int $userId, array $filters = [], int $perPage = 10): LengthAwarePaginator;
}


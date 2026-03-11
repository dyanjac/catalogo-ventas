<?php

namespace Modules\Orders\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Orders\Entities\Order;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function nextOrderNumber(string $series): int
    {
        return ((int) Order::query()->where('series', $series)->lockForUpdate()->max('order_number')) + 1;
    }

    public function create(array $data): Order
    {
        return Order::query()->create($data);
    }

    public function paginateUserOrders(int $userId, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $customer = trim((string) ($filters['customer'] ?? ''));
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        return Order::query()
            ->where('user_id', $userId)
            ->withCount('items')
            ->when($search !== '', function (Builder $query) use ($search) {
                $normalized = strtoupper(str_replace(' ', '', $search));

                $query->where(function (Builder $nested) use ($search, $normalized) {
                    $nested
                        ->whereRaw("CONCAT(series, '-', LPAD(order_number, 8, '0')) LIKE ?", ['%' . $normalized . '%'])
                        ->orWhere('transaction_id', 'like', '%' . $search . '%');
                });
            })
            ->when($customer !== '', function (Builder $query) use ($customer) {
                $query->where('shipping_address->name', 'like', '%' . $customer . '%');
            })
            ->when($dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}


<?php

namespace Modules\Orders\Repositories;

use App\Services\OrganizationContextService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Entities\Order;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private readonly OrganizationContextService $organizationContext)
    {
    }

    public function nextOrderNumber(string $series): int
    {
        $organizationId = (int) $this->organizationContext->currentOrganizationId();
        if ($organizationId < 1) {
            throw new \RuntimeException('La organizacion activa es obligatoria para numerar pedidos.');
        }

        $initial = ((int) Order::query()
            ->where('organization_id', $organizationId)
            ->where('series', $series)
            ->max('order_number')) + 1;

        DB::table('sales_order_counters')->insertOrIgnore([
            'organization_id' => $organizationId,
            'series' => $series,
            'next_number' => $initial,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('sales_order_counters')
            ->where('organization_id', $organizationId)
            ->where('series', $series)
            ->lockForUpdate()
            ->first();
        $number = (int) ($counter?->next_number ?? $initial);

        DB::table('sales_order_counters')
            ->where('organization_id', $organizationId)
            ->where('series', $series)
            ->update(['next_number' => $number + 1, 'updated_at' => now()]);

        return $number;
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
            ->forCurrentOrganization()
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

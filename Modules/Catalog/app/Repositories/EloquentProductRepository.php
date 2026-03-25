<?php

namespace Modules\Catalog\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Catalog\Entities\Product;
use Modules\Security\Services\SecurityBranchContextService;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly SecurityBranchContextService $branchContext)
    {
    }

    public function findBySlugOrFail(string $slug): Product
    {
        $branchId = $this->branchContext->currentBranchId();

        return Product::query()
            ->forCurrentOrganization()
            ->with($this->branchStockRelation($branchId))
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function findById(int $id): ?Product
    {
        $branchId = $this->branchContext->currentBranchId();

        return Product::query()
            ->forCurrentOrganization()
            ->with($this->branchStockRelation($branchId))
            ->find($id);
    }

    public function paginateActive(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $branchId = $this->branchContext->currentBranchId();

        $query = Product::query()
            ->forCurrentOrganization()
            ->active()
            ->with(array_merge(['category', 'unitMeasure', 'mainImage'], $this->branchStockRelation($branchId)))
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $categoryId = $filters['category_id'] ?? null;
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function featured(int $limit = 8): Collection
    {
        $branchId = $this->branchContext->currentBranchId();

        return Product::query()
            ->forCurrentOrganization()
            ->active()
            ->with(array_merge(['category', 'unitMeasure', 'mainImage'], $this->branchStockRelation($branchId)))
            ->latest('id')
            ->take($limit)
            ->get();
    }

    public function bestPrices(int $limit = 10): Collection
    {
        $branchId = $this->branchContext->currentBranchId();

        return Product::query()
            ->forCurrentOrganization()
            ->active()
            ->with(array_merge(['category', 'unitMeasure', 'mainImage'], $this->branchStockRelation($branchId)))
            ->orderByRaw('COALESCE(sale_price, price) asc')
            ->latest('id')
            ->take($limit)
            ->get();
    }

    private function branchStockRelation(?int $branchId): array
    {
        return [
            'branchStocks' => fn ($query) => $branchId ? $query->where('branch_id', $branchId)->where('is_active', true) : $query,
        ];
    }
}

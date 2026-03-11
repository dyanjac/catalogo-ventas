<?php

namespace Modules\Catalog\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Catalog\Entities\Product;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findBySlugOrFail(string $slug): Product
    {
        return Product::query()->where('slug', $slug)->firstOrFail();
    }

    public function findById(int $id): ?Product
    {
        return Product::query()->find($id);
    }

    public function paginateActive(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Product::query()
            ->active()
            ->with(['category', 'unitMeasure', 'mainImage'])
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
        return Product::query()
            ->active()
            ->with(['category', 'unitMeasure', 'mainImage'])
            ->latest('id')
            ->take($limit)
            ->get();
    }

    public function bestPrices(int $limit = 10): Collection
    {
        return Product::query()
            ->active()
            ->with(['category', 'unitMeasure', 'mainImage'])
            ->orderByRaw('COALESCE(sale_price, price) asc')
            ->latest('id')
            ->take($limit)
            ->get();
    }
}


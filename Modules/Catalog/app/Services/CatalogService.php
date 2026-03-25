<?php

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Repositories\ProductRepositoryInterface;

class CatalogService
{
    public function __construct(private readonly ProductRepositoryInterface $products)
    {
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function paginateCatalog(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->products->paginateActive($filters, $perPage);
    }

    public function categoriesWithProductsCount(): Collection
    {
        return Category::query()->forCurrentOrganization()->withCount('products')->orderBy('name')->get();
    }

    public function categoryBySlugOrFail(string $slug): Category
    {
        return Category::query()->forCurrentOrganization()->where('slug', $slug)->firstOrFail();
    }

    public function productBySlugOrFail(string $slug): Product
    {
        return $this->products->findBySlugOrFail($slug);
    }

    public function featuredProducts(int $limit = 8): Collection
    {
        return $this->products->featured($limit);
    }

    public function bestPriceProducts(int $limit = 10): Collection
    {
        return $this->products->bestPrices($limit);
    }

    public function homeGroups(int $limitCategories = 6, int $limitProducts = 8): Collection
    {
        return Category::query()
            ->forCurrentOrganization()
            ->whereHas('products', fn ($query) => $query->active()->forCurrentOrganization())
            ->with([
                'products' => fn ($query) => $query
                    ->forCurrentOrganization()
                    ->active()
                    ->with(['category', 'unitMeasure', 'mainImage'])
                    ->latest('id')
                    ->take($limitProducts),
            ])
            ->orderBy('name')
            ->take($limitCategories)
            ->get();
    }
}

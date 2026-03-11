<?php

namespace Modules\Catalog\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Catalog\Entities\Product;

interface ProductRepositoryInterface
{
    public function findBySlugOrFail(string $slug): Product;

    public function findById(int $id): ?Product;

    /**
     * @param array<string,mixed> $filters
     */
    public function paginateActive(array $filters = [], int $perPage = 12): LengthAwarePaginator;

    public function featured(int $limit = 8): Collection;

    public function bestPrices(int $limit = 10): Collection;
}


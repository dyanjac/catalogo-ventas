<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Catalog\Services\CatalogService;

class CatalogController extends Controller
{
    public function __construct(private readonly CatalogService $catalogService)
    {
    }

    public function index(): View
    {
        $filters = [
            'search' => request('q'),
            'category_id' => request('category_id'),
        ];

        return view('catalog.index', [
            'products' => $this->catalogService->paginateCatalog($filters, 12),
            'categories' => $this->catalogService->categoriesWithProductsCount(),
        ]);
    }

    public function show(string $product): View
    {
        $entity = $this->catalogService->productBySlugOrFail($product);
        abort_unless($entity->is_active, 404);
        $entity->load(['category', 'unitMeasure', 'mainImage', 'images']);

        return view('catalog.show', ['product' => $entity]);
    }
}

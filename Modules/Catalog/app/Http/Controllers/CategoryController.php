<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Catalog\Services\CatalogService;

class CategoryController extends Controller
{
    public function __construct(private readonly CatalogService $catalogService)
    {
    }

    public function show(string $category)
    {
        $category = $this->catalogService->categoryBySlugOrFail($category);
        $products = $category->products()->where('is_active', true)->paginate(12);

        return view('categories.show', compact('category', 'products'));
    }
}

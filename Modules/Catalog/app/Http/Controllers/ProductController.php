<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Catalog\Services\CatalogService;

class ProductController extends Controller
{
    public function __construct(private readonly CatalogService $catalogService)
    {
    }

    public function home()
    {
        $featured = $this->catalogService->featuredProducts(8);
        $categories = $this->catalogService->categoriesWithProductsCount();
        $homeGroups = $this->catalogService->homeGroups(6, 8);
        $bestPrices = $this->catalogService->bestPriceProducts(10);

        return view('home', compact('featured', 'categories', 'homeGroups', 'bestPrices'));

    }

    public function index()
    {
        $categories = $this->catalogService->categoriesWithProductsCount();
        $products = $this->catalogService->paginateCatalog([], 12);

        return view('products.index', compact('products', 'categories'));
    }

    public function category($slug)
    {
        $category = $this->catalogService->categoryBySlugOrFail($slug);
        $products = $category->products()->active()->paginate(9);

        return view('products.index', compact('products', 'category'));
    }

    public function show(string $product)
    {   
        $product = $this->catalogService->productBySlugOrFail($product);
        abort_unless($product->is_active, 404);
        $product->load(['category', 'unitMeasure', 'mainImage', 'images']);

        return view('products.show', compact('product'));
    }
}

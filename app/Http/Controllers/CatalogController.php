<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        $query = Product::query()
            ->active()
            ->with(['category', 'unitMeasure'])
            ->latest('id');

        if ($search = trim((string) request('q'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($categoryId = request('category_id')) {
            $query->where('category_id', $categoryId);
        }

        return view('catalog.index', [
            'products' => $query->paginate(12)->withQueryString(),
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);
        $product->load(['category', 'unitMeasure']);

        return view('catalog.show', compact('product'));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;

class ProductController extends Controller
{
    public function home()
    {
        $featured = Product::active()->latest('id')->take(8)->get();
        $categories = Category::withCount('products')->get();

        return view('home', compact('featured','categories'));

    }

    public function index()
    {
        //return view('products.index', [
        //    'products'   => Product::active()->latest('id')->paginate(12),
        //    'categories' => Category::all(),
        //]);

    $categories = Category::withCount('products')->get();
    $products = Product::active()->latest('id')->paginate(12);

    return view('products.index', compact('products', 'categories'));
    }

    public function category($slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $products = $category->products()->active()->paginate(9);

        return view('products.index', compact('products', 'category'));
    }

    public function show(Product $product)
    {   
        abort_unless($product->is_active, 404);
        $product->load(['category', 'unitMeasure']);

        return view('products.show', compact('product'));
    }
}

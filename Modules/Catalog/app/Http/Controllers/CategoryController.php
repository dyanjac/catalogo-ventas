<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
       public function show(Category $category)
    {
        $products = $category->products()->where('is_active', true)->paginate(12);
        return view('categories.show', compact('category','products'));
    }
}

<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'image_file' => ['required', 'image', 'max:4096'],
        ]);

        $path = $data['image_file']->store('products', 'public');

        ProductImage::where('product_id', $product->id)->update(['is_main' => false]);

        ProductImage::create([
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'path' => $path,
            'is_main' => true,
            'sort' => 0,
        ]);

        $product->update(['image' => $path]);

        return back()->with('success', 'Imagen principal actualizada.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        if ($image->path) {
            Storage::disk('public')->delete($image->path);
        }

        $wasMain = $image->is_main;
        $image->delete();

        if ($wasMain) {
            $nextMain = ProductImage::where('product_id', $product->id)->orderBy('sort')->orderBy('id')->first();

            if ($nextMain) {
                $nextMain->update(['is_main' => true]);
                $product->update(['image' => $nextMain->path]);
            } else {
                $product->update(['image' => null]);
            }
        }

        return back()->with('success', 'Imagen eliminada.');
    }
}

<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function view()
    {
        $cart = session('cart', []);
        $total = collect($cart)->sum(fn($i) => $i['quantity'] * $i['price']);
        return view('cart.view', compact('cart','total'));
    }

    public function addFromLink(Product $product, Request $request)
    {
        $qty = max(1, (int) $request->integer('quantity', 1));

        $cart = session('cart', []);
        $id = (string) $product->id;

        $cart[$id] = [
            'id'       => $product->id,
            'name'     => $product->name,
            'price'    => (float) ($product->sale_price ?? $product->price),
            'image'    => $product->image,
            'quantity' => ($cart[$id]['quantity'] ?? 0) + $qty,
        ];

        session(['cart' => $cart]);

        return redirect()->route('cart.view')->with('success', 'Producto agregado al carrito.');
    }

    public function add(Product $product, Request $request)
    {
        $qty = max(1, (int) $request->integer('quantity', 1));

        $cart = session('cart', []);
        $id = (string) $product->id;

        $cart[$id] = [
            'id'       => $product->id,
            'name'     => $product->name,
            'price'    => (float) ($product->sale_price ?? $product->price),
            'image'    => $product->image,
            'quantity' => ($cart[$id]['quantity'] ?? 0) + $qty,
        ];

        session(['cart' => $cart]);
        return back()->with('success', 'Producto agregado al carrito.');
    }

    public function update(Product $product, Request $request)
    {
        $qty = max(1, (int) $request->integer('quantity', 1));
        $cart = session('cart', []);
        $id = (string) $product->id;

        if (isset($cart[$id])) $cart[$id]['quantity'] = $qty;

        session(['cart' => $cart]);
        return back();
    }

    public function remove(Product $product)
    {
        $cart = session('cart', []);
        unset($cart[(string)$product->id]);
        session(['cart' => $cart]);
        return back();
    }

    public function clear()
    {
        session()->forget('cart');
        return back();
    }
}

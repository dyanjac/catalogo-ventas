<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Services\ProductInventoryService;
use Modules\Security\Services\SecurityBranchContextService;

class CartController extends Controller
{
    public function view()
    {
        $cart = session('cart', []);
        $total = collect($cart)->sum(fn ($i) => $i['quantity'] * $i['price']);

        return view('cart.view', compact('cart', 'total'));
    }

    public function addFromLink(Product $product, Request $request, ProductInventoryService $inventory, SecurityBranchContextService $branchContext)
    {
        return $this->addToCart($product, $request, $inventory, $branchContext, true);
    }

    public function add(Product $product, Request $request, ProductInventoryService $inventory, SecurityBranchContextService $branchContext)
    {
        return $this->addToCart($product, $request, $inventory, $branchContext, false);
    }

    public function update(Product $product, Request $request, ProductInventoryService $inventory, SecurityBranchContextService $branchContext)
    {
        $qty = max(1, (int) $request->integer('quantity', 1));
        $branchId = $branchContext->currentBranchId($request->user());
        $available = $inventory->availableStock($product, $branchId);

        if ($qty > $available) {
            return back()->withErrors([
                'cart' => "Stock insuficiente para {$product->name}. Disponible en la sucursal: {$available}.",
            ]);
        }

        $cart = session('cart', []);
        $id = (string) $product->id;

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = $qty;
        }

        session(['cart' => $cart]);

        return back();
    }

    public function remove(Product $product)
    {
        $cart = session('cart', []);
        unset($cart[(string) $product->id]);
        session(['cart' => $cart]);

        return back();
    }

    public function clear()
    {
        session()->forget('cart');

        return back();
    }

    private function addToCart(Product $product, Request $request, ProductInventoryService $inventory, SecurityBranchContextService $branchContext, bool $redirectToCart)
    {
        $qty = max(1, (int) $request->integer('quantity', 1));
        $cart = session('cart', []);
        $id = (string) $product->id;
        $currentQty = (int) ($cart[$id]['quantity'] ?? 0);
        $requestedQty = $currentQty + $qty;
        $branchId = $branchContext->currentBranchId($request->user());
        $available = $inventory->availableStock($product, $branchId);

        if ($requestedQty > $available) {
            $response = $redirectToCart ? redirect()->route('cart.view') : back();

            return $response->withErrors([
                'cart' => "Stock insuficiente para {$product->name}. Disponible en la sucursal: {$available}.",
            ]);
        }

        $cart[$id] = [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (float) ($product->sale_price ?? $product->price),
            'image' => $product->image,
            'quantity' => $requestedQty,
        ];

        session(['cart' => $cart]);

        $response = $redirectToCart ? redirect()->route('cart.view') : back();

        return $response->with('success', 'Producto agregado al carrito.');
    }
}

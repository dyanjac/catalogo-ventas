<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Services\ProductInventoryService;
use Modules\Commerce\Services\StorefrontCartService;
use Modules\Commerce\Services\StorefrontRouteService;
use Modules\Security\Services\SecurityBranchContextService;

class CartController extends Controller
{
    public function view(StorefrontCartService $storefrontCart)
    {
        $cart = $storefrontCart->all();
        $total = collect($cart)->sum(fn ($i) => $i['quantity'] * $i['price']);

        return view('cart.view', compact('cart', 'total'));
    }

    public function addFromLink(Product $product, Request $request, ProductInventoryService $inventory, SecurityBranchContextService $branchContext, StorefrontRouteService $storefrontRoutes, StorefrontCartService $storefrontCart)
    {
        return $this->addToCart($product, $request, $inventory, $branchContext, $storefrontRoutes, $storefrontCart, true);
    }

    public function add(Product $product, Request $request, ProductInventoryService $inventory, SecurityBranchContextService $branchContext, StorefrontRouteService $storefrontRoutes, StorefrontCartService $storefrontCart)
    {
        return $this->addToCart($product, $request, $inventory, $branchContext, $storefrontRoutes, $storefrontCart, false);
    }

    public function update(Product $product, Request $request, ProductInventoryService $inventory, SecurityBranchContextService $branchContext, StorefrontCartService $storefrontCart)
    {
        $qty = max(1, (int) $request->integer('quantity', 1));
        $branchId = $branchContext->currentBranchId($request->user());
        $available = $inventory->availableStock($product, $branchId);

        if ($qty > $available) {
            return back()->withErrors([
                'cart' => "Stock insuficiente para {$product->name}. Disponible en la sucursal: {$available}.",
            ]);
        }

        $cart = $storefrontCart->all();
        $id = (string) $product->id;

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = $qty;
        }

        $storefrontCart->replace($cart);

        return back();
    }

    public function remove(Product $product, StorefrontCartService $storefrontCart)
    {
        $cart = $storefrontCart->all();
        unset($cart[(string) $product->id]);
        $storefrontCart->replace($cart);

        return back();
    }

    public function clear(StorefrontCartService $storefrontCart)
    {
        $storefrontCart->forget();

        return back();
    }

    private function addToCart(Product $product, Request $request, ProductInventoryService $inventory, SecurityBranchContextService $branchContext, StorefrontRouteService $storefrontRoutes, StorefrontCartService $storefrontCart, bool $redirectToCart)
    {
        $qty = max(1, (int) $request->integer('quantity', 1));
        $cart = $storefrontCart->all();
        $id = (string) $product->id;
        $currentQty = (int) ($cart[$id]['quantity'] ?? 0);
        $requestedQty = $currentQty + $qty;
        $branchId = $branchContext->currentBranchId($request->user());
        $available = $inventory->availableStock($product, $branchId);

        if ($requestedQty > $available) {
            $response = $redirectToCart ? redirect()->to($storefrontRoutes->route('cart.view')) : back();

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

        $storefrontCart->replace($cart);

        $response = $redirectToCart ? redirect()->to($storefrontRoutes->route('cart.view')) : back();

        return $response->with('success', 'Producto agregado al carrito.');
    }
}

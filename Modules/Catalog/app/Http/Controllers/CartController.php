<?php

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DocumentTotals;
use App\Services\OrganizationContextService;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Services\ProductInventoryService;
use Modules\Commerce\Services\StorefrontCartService;
use Modules\Commerce\Services\StorefrontRouteService;
use Modules\Security\Services\SecurityBranchContextService;

class CartController extends Controller
{
    public function view(StorefrontCartService $storefrontCart, OrganizationContextService $organizationContext, DocumentTotals $documentTotals, SecurityBranchContextService $branchContext, Request $request)
    {
        $cart = $storefrontCart->all();
        $organizationId = (int) ($organizationContext->publicStorefront()?->id ?? 0);
        $totals = $organizationId > 0 && $cart !== []
            ? $documentTotals->calculate(
                array_map(fn (array $item): array => ['quantity' => $item['quantity'], 'unit_price' => $item['price']], array_values($cart)),
                (string) config('orders.checkout.discount', 0),
                (string) config('orders.checkout.shipping', 0),
                (string) config('orders.checkout.tax_rate', 0.18),
                $organizationId,
                $branchContext->currentBranchId($request->user()),
            )
            : ['subtotal' => '0.00', 'discount' => '0.00', 'tax' => '0.00', 'shipping' => '0.00', 'total' => '0.00', 'lines' => []];

        return view('cart.view', compact('cart', 'totals'));
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
        $qty = Decimal::quantity($request->validate(['quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/', 'numeric', 'gt:0']])['quantity']);
        $branchId = $branchContext->currentBranchId($request->user());
        $available = $inventory->availableStock($product, $branchId);

        if (Decimal::compare($qty, $available, 4) > 0) {
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
        $qty = Decimal::quantity($request->validate(['quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/', 'numeric', 'gt:0']])['quantity']);
        $cart = $storefrontCart->all();
        $id = (string) $product->id;
        $currentQty = $cart[$id]['quantity'] ?? 0;
        $requestedQty = Decimal::quantity(Decimal::add($currentQty, $qty, 4));
        $branchId = $branchContext->currentBranchId($request->user());
        $available = $inventory->availableStock($product, $branchId);

        if (Decimal::compare($requestedQty, $available, 4) > 0) {
            $response = $redirectToCart ? redirect()->to($storefrontRoutes->route('cart.view')) : back();

            return $response->withErrors([
                'cart' => "Stock insuficiente para {$product->name}. Disponible en la sucursal: {$available}.",
            ]);
        }

        $cart[$id] = [
            'id' => $product->id,
            'name' => $product->name,
            'price' => Decimal::assertScale($product->sale_price ?? $product->price, 6),
            'image' => $product->image,
            'quantity' => $requestedQty,
        ];

        $storefrontCart->replace($cart);

        $response = $redirectToCart ? redirect()->to($storefrontRoutes->route('cart.view')) : back();

        return $response->with('success', 'Producto agregado al carrito.');
    }
}

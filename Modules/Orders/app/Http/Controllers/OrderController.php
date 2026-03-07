<?php

namespace Modules\Orders\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function showCheckout()
    {
        $cart = session('cart', []);
        abort_if(empty($cart), 400, 'Carrito vacío');

        $checkoutData = $this->buildCheckoutData($cart);

        if ($checkoutData['has_issues']) {
            return redirect()
                ->route('cart.view')
                ->with('error', 'Actualizamos tu carrito antes de continuar. Revisa disponibilidad y vuelve a intentar.');
        }

        return view('checkout.index', [
            'cart' => $checkoutData['items'],
            'subtotal' => $checkoutData['subtotal'],
        ]);
    }

    public function checkout(Request $request)
    {
        $cart = session('cart', []);
        abort_if(empty($cart), 400, 'Carrito vacío');

        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'series' => ['nullable', 'string', 'max:4'],
            'currency' => ['nullable', 'in:PEN,USD'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'payment_method' => ['nullable', 'in:cash,transfer,card,yape'],
            'payment_status' => ['nullable', 'in:pending,paid,failed,refunded'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);

        $series = strtoupper(trim((string) $request->input('series', 'PED')));
        if ($series === '') {
            $series = 'PED';
        }
        $currency = strtoupper(trim((string) $request->input('currency', 'PEN')));
        $paymentMethod = (string) $request->input('payment_method', 'cash');
        $paymentStatus = (string) $request->input('payment_status', 'pending');
        $shipping = round((float) $request->input('shipping', 0), 2);
        $discount = round((float) $request->input('discount', 0), 2);
        $taxRate = (float) $request->input('tax_rate', 0.18);

        $checkoutData = $this->buildCheckoutData($cart, true);
        $cart = $checkoutData['items'];
        $subtotal = $checkoutData['subtotal'];
        $discount = min($discount, $subtotal);
        $taxableBase = max(0, $subtotal - $discount);
        $tax = round($taxableBase * $taxRate, 2);
        $total = round($taxableBase + $shipping + $tax, 2);

        $createdOrderNumber = null;

        DB::transaction(function () use (
            $cart,
            $subtotal,
            $shipping,
            $discount,
            $tax,
            $total,
            $request,
            $series,
            $currency,
            $paymentMethod,
            $paymentStatus,
            &$createdOrderNumber
        ) {
            $productIds = collect($cart)->pluck('id')->all();
            $products = Product::query()
                ->whereKey($productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Product $product) => (string) $product->id);

            foreach ($cart as $item) {
                $product = $products->get((string) $item['id']);
                $qty = (int) $item['quantity'];

                if (! $product || ! $product->is_active || $product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'cart' => ["El producto {$item['name']} ya no tiene stock suficiente para completar el pedido."],
                    ]);
                }
            }

            $nextOrderNumber = ((int) Order::where('series', $series)->lockForUpdate()->max('order_number')) + 1;
            $paidAt = $paymentStatus === 'paid' ? now() : null;
            $order = Order::create([
                'user_id' => (int) auth()->id(),
                'series' => $series,
                'order_number' => $nextOrderNumber,
                'status' => 'confirmed',
                'currency' => $currency,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping' => $shipping,
                'tax' => $tax,
                'total' => $total,
                'shipping_address' => $request->only(['name', 'address', 'city', 'phone']),
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
                'transaction_id' => $request->input('transaction_id'),
                'observations' => $request->input('observations'),
            ]);
            $createdOrderNumber = $order->series . '-' . str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT);

            $discountRatio = $subtotal > 0 ? ($discount / $subtotal) : 0;
            $taxableBase = max(0, $subtotal - $discount);
            $taxRatio = $taxableBase > 0 ? ($tax / $taxableBase) : 0;

            foreach ($cart as $item) {
                $qty = (int) $item['quantity'];
                $unitPrice = (float) $item['price'];
                $lineSubtotal = round($qty * $unitPrice, 2);
                $lineDiscount = round($lineSubtotal * $discountRatio, 2);
                $lineTaxable = max(0, $lineSubtotal - $lineDiscount);
                $lineTax = round($lineTaxable * $taxRatio, 2);
                $lineTotal = round($lineTaxable + $lineTax, 2);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => (int) $item['id'],
                    'currency' => $currency,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ]);

                $products->get((string) $item['id'])->decrement('stock', $qty);
            }
        });
         
        session()->forget('cart');

        return redirect()
            ->route('orders.mine')
            ->with('success', 'Pedido registrado correctamente.')
            ->with('latest_order_number', $createdOrderNumber);
    }

    public function myOrders(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $customer = trim((string) $request->input('customer', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $orders = Order::query()
            ->whereBelongsTo(auth()->user())
            ->withCount('items')
            ->when($search !== '', function (Builder $query) use ($search) {
                $normalized = strtoupper(str_replace(' ', '', $search));

                $query->where(function (Builder $nested) use ($search, $normalized) {
                    $nested
                        ->whereRaw("CONCAT(series, '-', LPAD(order_number, 8, '0')) LIKE ?", ['%' . $normalized . '%'])
                        ->orWhere('transaction_id', 'like', '%' . $search . '%');
                });
            })
            ->when($customer !== '', function (Builder $query) use ($customer) {
                $query->where('shipping_address->name', 'like', '%' . $customer . '%');
            })
            ->when($dateFrom, function (Builder $query) use ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function (Builder $query) use ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('orders.mine', compact('orders', 'search', 'customer', 'dateFrom', 'dateTo'));
    }

    public function show(Order $order)
    {
        abort_unless($order->user_id === (int) auth()->id(), 403);

        $order->load(['items.product']);

        return view('orders.show', compact('order'));
    }

    private function buildCheckoutData(array $cart, bool $requireStock = false): array
    {
        $items = [];
        $hasIssues = false;
        $products = Product::query()
            ->whereKey(array_keys($cart))
            ->get()
            ->keyBy(fn (Product $product) => (string) $product->id);

        foreach ($cart as $item) {
            $product = $products->get((string) ($item['id'] ?? ''));
            $qty = max(1, (int) ($item['quantity'] ?? 0));

            if (! $product || ! $product->is_active) {
                $hasIssues = true;
                continue;
            }

            if ($requireStock && $product->stock < $qty) {
                throw ValidationException::withMessages([
                    'cart' => ["El producto {$product->name} solo tiene {$product->stock} unidades disponibles."],
                ]);
            }

            if (! $requireStock && $product->stock < $qty) {
                $hasIssues = true;
            }

            $items[] = [
                'id' => (string) $product->id,
                'name' => $product->name,
                'image' => $product->primary_image_path,
                'price' => (float) ($product->display_price ?? $product->price ?? 0),
                'quantity' => $qty,
                'stock' => (int) $product->stock,
            ];
        }

        session(['cart' => collect($items)->keyBy('id')->all()]);

        return [
            'items' => $items,
            'subtotal' => round((float) collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']), 2),
            'has_issues' => $hasIssues || count($items) !== count($cart),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;

class OrderController extends Controller
{
    public function showCheckout()
    {
        $cart = session('cart', []);
        abort_if(empty($cart), 400, 'Carrito vacío');

        $subtotal = round((float) collect($cart)->sum(fn ($i) => ((int) $i['quantity']) * ((float) $i['price'])), 2);

        return view('checkout.index', [
            'cart' => $cart,
            'subtotal' => $subtotal,
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
            'currency' => ['nullable', 'string', 'size:3'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'payment_method' => ['nullable', 'string', 'max:30'],
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

        $subtotal = round((float) collect($cart)->sum(fn ($i) => ((int) $i['quantity']) * ((float) $i['price'])), 2);
        $discount = min($discount, $subtotal);
        $taxableBase = max(0, $subtotal - $discount);
        $tax = round($taxableBase * $taxRate, 2);
        $total = round($taxableBase + $shipping + $tax, 2);

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
                $paymentStatus
            ) {
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
                    'shipping_address' => $request->only(['name','address','city','phone']),
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'paid_at' => $paidAt,
                    'transaction_id' => $request->input('transaction_id'),
                    'observations' => $request->input('observations'),
                ]);

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

                    Product::whereKey($item['id'])->decrement('stock', $qty);
                }
            });
         
        session()->forget('cart');
        return redirect()->route('orders.mine')->with('success', 'Pedido registrado correctamente.');
    }

    public function myOrders()
    {
        $orders = Order::with('items.product')->whereBelongsTo(auth()->user())->latest()->paginate(10);
        return view('orders.mine', compact('orders'));
    }
}

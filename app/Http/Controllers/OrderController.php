<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;

class OrderController extends Controller
{
    public function checkout(Request $request)
    {
        $cart = session('cart', []);
        abort_if(empty($cart), 400, 'Carrito vacío');

        $request->validate([
            'name'    => ['required','string','max:120'],
            'address' => ['required','string','max:200'],
            'city'    => ['required','string','max:100'],
            'phone'   => ['required','string','max:30'],
        ]);

        $subtotal = collect($cart)->sum(fn($i)=>$i['quantity']*$i['price']);
        $shipping = 0.00;
        $total    = $subtotal + $shipping;


            DB::transaction(function () use ($cart, $subtotal, $shipping, $total, $request) {
                $order = Order::create([
                    'user_id'          => (int) auth()->id(),
                    'status'           => 'pending',
                    'subtotal'         => $subtotal,
                    'shipping'         => $shipping,
                    'total'            => $total,
                    'shipping_address' => $request->only(['name','address','city','phone']),
                ]);

                foreach ($cart as $item) {
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => (int) $item['id'],
                        'quantity'   => (int) $item['quantity'],
                        'unit_price' => (float) $item['price'],
                        'line_total' => (float) $item['quantity'] * (float) $item['price'],
                    ]);

                    Product::whereKey($item['id'])->decrement('stock', (int) $item['quantity']);
                }
            });
         
        session()->forget('cart');
        return redirect()->route('orders.mine')->with('success', 'Pedido creado');
    }

    public function myOrders()
    {
        $orders = Order::with('items.product')->whereBelongsTo(auth()->user())->latest()->paginate(10);
        return view('orders.mine', compact('orders'));
    }
}

<?php

namespace Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Orders\Entities\Order;
use Modules\Orders\Services\OrderCheckoutService;
use Modules\Orders\Services\OrderQueryService;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderCheckoutService $checkoutService,
        private readonly OrderQueryService $orderQueryService
    ) {
    }

    public function showCheckout()
    {
        $cart = session('cart', []);
        abort_if(empty($cart), 400, 'Carrito vacío');

        $checkoutData = $this->checkoutService->buildCheckoutData($cart);
        session(['cart' => collect($checkoutData['items'])->keyBy('id')->all()]);

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
        $result = $this->checkoutService->checkout([
            'user_id' => (int) auth()->id(),
            'name' => (string) $request->input('name'),
            'address' => (string) $request->input('address'),
            'city' => (string) $request->input('city'),
            'phone' => (string) $request->input('phone'),
            'series' => $series,
            'currency' => $currency,
            'shipping' => $request->input('shipping', 0),
            'discount' => $request->input('discount', 0),
            'tax_rate' => $request->input('tax_rate', 0.18),
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'transaction_id' => $request->input('transaction_id'),
            'observations' => $request->input('observations'),
        ], $cart);
         
        session()->forget('cart');

        return redirect()
            ->route('orders.mine')
            ->with('success', 'Pedido registrado correctamente.')
            ->with('latest_order_number', $result['order_number']);
    }

    public function myOrders(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $customer = trim((string) $request->input('customer', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $orders = $this->orderQueryService->myOrders((int) auth()->id(), [
            'search' => $search,
            'customer' => $customer,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ], 10);

        return view('orders.mine', compact('orders', 'search', 'customer', 'dateFrom', 'dateTo'));
    }

    public function show(Order $order)
    {
        $order = $this->orderQueryService->myOrderDetailOrFail((int) auth()->id(), (int) $order->id);

        return view('orders.show', compact('order'));
    }
}

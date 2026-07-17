<?php

namespace Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Orders\Entities\Order;
use Modules\Orders\Services\OrderCheckoutService;
use Modules\Orders\Services\OrderQueryService;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderCheckoutService $checkoutService,
        private readonly OrderQueryService $orderQueryService,
        private readonly OrganizationContextService $organizationContext,
    ) {
    }

    public function showCheckout()
    {
        if ($this->organizationContext->isSuspended()) {
            return redirect()
                ->route('cart.view')
                ->with('error', 'La organización actual está suspendida y no puede continuar al checkout.');
        }

        $cart = session('cart', []);
        abort_if(empty($cart), 400, 'Carrito vacío');

        $checkoutData = $this->checkoutService->buildCheckoutData($cart);
        session(['cart' => collect($checkoutData['items'])->keyBy('id')->all()]);
        session()->put('checkout_idempotency_key', session('checkout_idempotency_key') ?: (string) Str::uuid());

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
        if ($this->organizationContext->isSuspended()) {
            return redirect()
                ->route('cart.view')
                ->with('error', 'La organización actual está suspendida y no puede registrar pedidos.');
        }

        $cart = session('cart', []);
        abort_if(empty($cart), 400, 'Carrito vacío');

        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'payment_method' => ['nullable', 'in:cash,transfer,card,yape'],
            'idempotency_key' => ['nullable', 'string', 'max:160'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);

        $paymentMethod = (string) $request->input('payment_method', 'cash');
        $result = $this->checkoutService->checkout([
            'user_id' => (int) auth()->id(),
            'name' => (string) $request->input('name'),
            'address' => (string) $request->input('address'),
            'city' => (string) $request->input('city'),
            'phone' => (string) $request->input('phone'),
            'payment_method' => $paymentMethod,
            'idempotency_key' => (string) ($request->input('idempotency_key') ?: session('checkout_idempotency_key') ?: Str::uuid()),
            'observations' => $request->input('observations'),
        ], $cart);

        session()->forget(['cart', 'checkout_idempotency_key']);

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

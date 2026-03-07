<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');
        $paymentStatus = (string) $request->input('payment_status', '');

        $orders = Order::query()
            ->with(['user'])
            ->when($search !== '', function ($query) use ($search) {
                $normalized = strtoupper(str_replace(' ', '', $search));

                $query->where(function ($sub) use ($search, $normalized) {
                    $sub->whereRaw("CONCAT(series, '-', LPAD(order_number, 8, '0')) LIKE ?", ['%' . $normalized . '%'])
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhere('transaction_id', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'search', 'status', 'paymentStatus'));
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.product']);

        return view('admin.orders.show', compact('order'));
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:confirmed,processing,delivered,cancelled,pending'],
            'payment_status' => ['required', 'in:pending,paid,failed,refunded'],
            'payment_method' => ['required', 'in:cash,transfer,card,yape'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['payment_status'] === 'paid' && ! $order->paid_at) {
            $data['paid_at'] = now();
        }

        if ($data['payment_status'] !== 'paid') {
            $data['paid_at'] = null;
        }

        $order->update($data);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Pedido actualizado correctamente.');
    }
}

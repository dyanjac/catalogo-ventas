<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\SimplePdfBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('admin.orders.index');
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

    public function downloadPdf(Order $order)
    {
        $order->loadMissing(['user', 'items.product']);

        $lines = [
            'Pedido: ' . $order->series . '-' . str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT),
            'Fecha: ' . optional($order->created_at)->format('d/m/Y H:i'),
            'Cliente: ' . ($order->user?->name ?? 'Sin usuario'),
            'Email: ' . ($order->user?->email ?? '-'),
            'Estado: ' . strtoupper((string) $order->status),
            'Pago: ' . strtoupper((string) $order->payment_status),
            'Metodo pago: ' . strtoupper((string) $order->payment_method),
            'Moneda: ' . $order->currency,
            'Subtotal: ' . number_format((float) $order->subtotal, 2),
            'Descuento: ' . number_format((float) $order->discount, 2),
            'IGV: ' . number_format((float) $order->tax, 2),
            'Envio: ' . number_format((float) $order->shipping, 2),
            'Total: ' . number_format((float) $order->total, 2),
            '--- Detalle ---',
        ];

        foreach ($order->items as $item) {
            $lines[] = ($item->product?->name ?? 'Producto eliminado')
                . ' | Cant: ' . $item->quantity
                . ' | P.Unit: ' . number_format((float) $item->unit_price, 2)
                . ' | Total: ' . number_format((float) $item->line_total, 2);
        }

        $pdf = SimplePdfBuilder::fromLines('Pedido ' . $order->series . '-' . str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT), $lines);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="pedido-' . $order->series . '-' . str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT) . '.pdf"',
        ]);
    }
}

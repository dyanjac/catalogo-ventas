<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Accounting\Services\SalesAccountingService;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Models\BillingSetting;
use Modules\Billing\Services\ElectronicBillingService;
use Throwable;

class SalesPosController extends Controller
{
    public function index(): View
    {
        return view('sales::pos.index', [
            'products' => Product::query()
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'sale_price', 'price', 'stock']),
            'defaultTaxRate' => (float) config('sales.default_tax_rate', 0.18),
            'defaultCurrency' => config('sales.default_currency', 'PEN'),
        ]);
    }

    public function store(
        Request $request,
        ElectronicBillingService $electronicBilling,
        SalesAccountingService $salesAccounting
    ): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'in:order,boleta,factura'],
            'currency' => ['required', 'in:PEN,USD'],
            'payment_method' => ['required', 'in:cash,transfer,card,yape'],
            'payment_status' => ['required', 'in:pending,paid,failed,refunded'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'customer.name' => ['required', 'string', 'max:120'],
            'customer.address' => ['required', 'string', 'max:200'],
            'customer.city' => ['required', 'string', 'max:100'],
            'customer.phone' => ['required', 'string', 'max:30'],
            'customer.document_type' => ['nullable', 'in:DNI,RUC,CE,PAS'],
            'customer.document_number' => ['nullable', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['document_type'] === 'boleta') {
            if (empty($data['customer']['document_type']) || empty($data['customer']['document_number'])) {
                throw ValidationException::withMessages([
                    'customer.document_number' => 'Para emitir boleta electrónica se requiere tipo y número de documento del cliente.',
                ]);
            }
        }

        if ($data['document_type'] === 'factura') {
            if (($data['customer']['document_type'] ?? null) !== 'RUC' || empty($data['customer']['document_number'])) {
                throw ValidationException::withMessages([
                    'customer.document_type' => 'Para factura electrónica el cliente debe tener tipo de documento RUC.',
                ]);
            }
        }

        $taxRate = (float) ($data['tax_rate'] ?? config('sales.default_tax_rate', 0.18));
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $shipping = round((float) ($data['shipping'] ?? 0), 2);

        $createdOrder = null;
        $createdBillingDocument = null;
        $payload = [];

        DB::transaction(function () use (&$createdOrder, &$createdBillingDocument, &$payload, $data, $taxRate, $discount, $shipping): void {
            $items = collect($data['items']);
            $products = Product::query()
                ->whereIn('id', $items->pluck('product_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $normalizedItems = $this->normalizeItems($items, $products);
            $subtotal = round((float) $normalizedItems->sum(fn (array $line) => $line['line_subtotal']), 2);
            $discountAmount = min($discount, $subtotal);
            $taxableBase = max(0, $subtotal - $discountAmount);
            $tax = round($taxableBase * $taxRate, 2);
            $total = round($taxableBase + $tax + $shipping, 2);
            $series = $this->resolveOrderSeries($data['document_type']);

            $nextOrderNumber = ((int) Order::query()->where('series', $series)->lockForUpdate()->max('order_number')) + 1;
            $paidAt = $data['payment_status'] === 'paid' ? now() : null;

            $createdOrder = Order::query()->create([
                'user_id' => (int) auth()->id(),
                'series' => $series,
                'order_number' => $nextOrderNumber,
                'status' => 'confirmed',
                'currency' => $data['currency'],
                'subtotal' => $subtotal,
                'discount' => $discountAmount,
                'shipping' => $shipping,
                'tax' => $tax,
                'total' => $total,
                'shipping_address' => [
                    'name' => $data['customer']['name'],
                    'address' => $data['customer']['address'],
                    'city' => $data['customer']['city'],
                    'phone' => $data['customer']['phone'],
                    'document_type' => $data['customer']['document_type'] ?? null,
                    'document_number' => $data['customer']['document_number'] ?? null,
                ],
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_status'],
                'paid_at' => $paidAt,
                'observations' => $data['observations'] ?? null,
            ]);

            $discountRatio = $subtotal > 0 ? $discountAmount / $subtotal : 0;
            $taxRatio = $taxableBase > 0 ? $tax / $taxableBase : 0;

            foreach ($normalizedItems as $line) {
                $lineDiscount = round($line['line_subtotal'] * $discountRatio, 2);
                $lineTaxable = max(0, $line['line_subtotal'] - $lineDiscount);
                $lineTax = round($lineTaxable * $taxRatio, 2);
                $lineTotal = round($lineTaxable + $lineTax, 2);

                OrderItem::query()->create([
                    'order_id' => $createdOrder->id,
                    'product_id' => $line['product']->id,
                    'currency' => $data['currency'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ]);

                $line['product']->decrement('stock', $line['quantity']);
            }

            if ($data['document_type'] !== 'order') {
                $billingSetting = BillingSetting::query()->first();
                if (! $billingSetting || ! $billingSetting->enabled) {
                    throw ValidationException::withMessages([
                        'document_type' => 'La facturación electrónica está desactivada. Actívala antes de emitir boletas o facturas.',
                    ]);
                }

                [$billingSeries, $billingNumber] = $this->nextDocumentCorrelative($data['document_type'], $billingSetting);

                $createdBillingDocument = BillingDocument::query()->create([
                    'order_id' => $createdOrder->id,
                    'provider' => $billingSetting->provider,
                    'document_type' => $data['document_type'],
                    'series' => $billingSeries,
                    'number' => $billingNumber,
                    'issue_date' => now()->toDateString(),
                    'customer_document_type' => $data['customer']['document_type'] ?? null,
                    'customer_document_number' => $data['customer']['document_number'] ?? null,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'currency' => $data['currency'],
                    'status' => 'queued',
                ]);

                $payload = [
                    'order_id' => $createdOrder->id,
                    'document_type' => $data['document_type'],
                    'series' => $billingSeries,
                    'number' => $billingNumber,
                    'issue_date' => now()->toDateString(),
                    'currency' => $data['currency'],
                    'customer' => [
                        'name' => $data['customer']['name'],
                        'address' => $data['customer']['address'],
                        'city' => $data['customer']['city'],
                        'phone' => $data['customer']['phone'],
                        'document_type' => $data['customer']['document_type'] ?? null,
                        'document_number' => $data['customer']['document_number'] ?? null,
                    ],
                    'totals' => [
                        'subtotal' => $subtotal,
                        'discount' => $discountAmount,
                        'tax' => $tax,
                        'shipping' => $shipping,
                        'total' => $total,
                    ],
                    'items' => $normalizedItems->map(function (array $line) {
                        return [
                            'product_id' => $line['product']->id,
                            'sku' => $line['product']->sku,
                            'name' => $line['product']->name,
                            'quantity' => $line['quantity'],
                            'unit_price' => $line['unit_price'],
                            'line_subtotal' => $line['line_subtotal'],
                        ];
                    })->values()->all(),
                ];
            }
        });

        if ($createdBillingDocument) {
            try {
                $billingResult = $electronicBilling->issueOrQueue($createdBillingDocument, $payload);

                if ((bool) ($billingResult['queued'] ?? false)) {
                    return back()->with('warning', 'Venta registrada. Emisión electrónica en cola ('.$billingResult['connection'].'/'.$billingResult['queue'].').');
                }

                if (! ($billingResult['ok'] ?? false)) {
                    return back()->with('warning', 'Venta registrada, pero la emisión electrónica quedó pendiente: '.($billingResult['message'] ?? 'Error no especificado.'));
                }

                $accounting = $salesAccounting->postIssuedSale($createdOrder, $createdBillingDocument);
                if (! $accounting['created']) {
                    return back()->with('warning', 'Venta y comprobante emitidos. Asiento contable no generado: ' . $accounting['message']);
                }

                return back()->with('success', 'Venta, comprobante y asiento contable generados correctamente.');
            } catch (Throwable $e) {
                report($e);

                return back()->with('warning', 'Venta registrada, pero ocurrió un error al enviar el comprobante al proveedor.');
            }
        }

        return back()->with('success', 'Pedido de venta registrado correctamente.');
    }

    /**
     * @param Collection<int,array<string,mixed>> $items
     * @param Collection<int,Product> $products
     * @return Collection<int,array{product:Product,quantity:int,unit_price:float,line_subtotal:float}>
     */
    private function normalizeItems(Collection $items, Collection $products): Collection
    {
        return $items->map(function (array $item) use ($products): array {
            $product = $products->get((int) $item['product_id']);
            $quantity = (int) $item['quantity'];

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'items' => ["El producto con ID {$item['product_id']} no está disponible."],
                ]);
            }

            if ($product->stock < $quantity) {
                throw ValidationException::withMessages([
                    'items' => ["Stock insuficiente para {$product->name}. Disponible: {$product->stock}."],
                ]);
            }

            $unitPrice = isset($item['unit_price']) && (float) $item['unit_price'] > 0
                ? round((float) $item['unit_price'], 2)
                : round((float) ($product->sale_price ?? $product->price ?? 0), 2);

            return [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => round($unitPrice * $quantity, 2),
            ];
        });
    }

    private function resolveOrderSeries(string $documentType): string
    {
        return match ($documentType) {
            'factura' => 'FAC',
            'boleta' => 'BOL',
            default => strtoupper((string) config('sales.default_series', 'POS')),
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function nextDocumentCorrelative(string $documentType, BillingSetting $setting): array
    {
        $series = $documentType === 'factura'
            ? strtoupper((string) ($setting->invoice_series ?: 'F001'))
            : strtoupper((string) ($setting->receipt_series ?: 'B001'));

        $max = BillingDocument::query()
            ->where('document_type', $documentType)
            ->where('series', $series)
            ->lockForUpdate()
            ->max('number');

        $next = ((int) $max) + 1;

        return [$series, str_pad((string) $next, 8, '0', STR_PAD_LEFT)];
    }
}

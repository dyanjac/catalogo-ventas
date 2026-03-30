<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Accounting\Services\SalesAccountingService;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Models\BillingSetting;
use Modules\Billing\Services\ElectronicBillingService;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Services\ProductInventoryService;
use Modules\Orders\Entities\Order;
use Modules\Orders\Entities\OrderItem;
use Modules\Sales\Services\CustomerDocumentLookupService;
use Modules\Security\Services\SecurityBranchContextService;
use Throwable;

class SalesPosController extends Controller
{
    public function __construct(
        private readonly ProductInventoryService $inventory,
        private readonly SecurityBranchContextService $branchContext,
        private readonly OrganizationContextService $organizationContext,
    ) {
    }

    public function index(): View
    {
        return view('sales::pos.index');
    }

    public function lookupCustomerDocument(Request $request, CustomerDocumentLookupService $lookupService): JsonResponse
    {
        if ($this->organizationContext->isSuspended()) {
            return response()->json([
                'ok' => false,
                'message' => 'La organización actual está suspendida y no puede operar consultas del POS.',
            ], 423);
        }

        $data = $request->validate([
            'document_type' => ['required', 'in:DNI,RUC'],
            'document_number' => ['required', 'string', 'max:20'],
        ]);

        $result = $lookupService->lookup($data['document_type'], $data['document_number']);

        if (! $result['ok']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function store(
        Request $request,
        ElectronicBillingService $electronicBilling,
        SalesAccountingService $salesAccounting
    ): RedirectResponse {
        if ($this->organizationContext->isSuspended()) {
            throw ValidationException::withMessages([
                'document_type' => 'La organización actual está suspendida y no puede registrar ventas por POS.',
            ]);
        }

        $organizationId = $this->organizationContext->currentOrganizationId();

        $data = $request->validate([
            'document_type' => ['required', 'in:order,boleta,factura'],
            'currency' => ['required', 'in:PEN,USD'],
            'payment_method' => ['required', 'in:cash,transfer,card,yape'],
            'payment_status' => ['required', 'in:pending,paid,failed,refunded'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'customer.name' => ['required', 'string', 'max:120'],
            'customer.address' => ['nullable', 'string', 'max:200'],
            'customer.city' => ['nullable', 'string', 'max:100'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.document_type' => ['nullable', 'in:DNI,RUC,CE,PAS'],
            'customer.document_number' => ['nullable', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('organization_id', $organizationId)],
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
        $branchId = (int) ($this->branchContext->currentBranchId($request->user()) ?: 0);

        $createdOrder = null;
        $createdBillingDocument = null;
        $payload = [];

        DB::transaction(function () use (&$createdOrder, &$createdBillingDocument, &$payload, $data, $taxRate, $discount, $shipping, $branchId, $organizationId): void {
            $items = collect($data['items']);
            $products = Product::query()
                ->forCurrentOrganization()
                ->with(['branchStocks' => fn ($query) => $query->where('branch_id', $branchId)])
                ->whereIn('id', $items->pluck('product_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $normalizedItems = $this->normalizeItems($items, $products, $branchId);
            $subtotal = round((float) $normalizedItems->sum(fn (array $line) => $line['line_subtotal']), 2);
            $discountAmount = min($discount, $subtotal);
            $taxableBase = max(0, $subtotal - $discountAmount);
            $tax = round($taxableBase * $taxRate, 2);
            $total = round($taxableBase + $tax + $shipping, 2);
            $series = $this->resolveOrderSeries($data['document_type']);

            $nextOrderNumber = ((int) Order::query()
                ->forCurrentOrganization()
                ->where('series', $series)
                ->lockForUpdate()
                ->max('order_number')) + 1;
            $paidAt = $data['payment_status'] === 'paid' ? now() : null;

            $createdOrder = Order::query()->create([
                'organization_id' => $organizationId,
                'user_id' => (int) auth()->id(),
                'branch_id' => $branchId ?: null,
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
                    'address' => $data['customer']['address'] ?? null,
                    'city' => $data['customer']['city'] ?? null,
                    'phone' => $data['customer']['phone'] ?? null,
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

                $this->inventory->decrementBranchStock($line['product'], $branchId, $line['quantity'], [
                    'reason' => 'pos_sale',
                    'performed_by' => auth()->id(),
                    'reference_type' => Order::class,
                    'reference_id' => $createdOrder->id,
                    'reference_code' => $createdOrder->series.'-'.str_pad((string) $createdOrder->order_number, 8, '0', STR_PAD_LEFT),
                    'meta' => [
                        'channel' => 'pos',
                        'document_type' => $data['document_type'],
                    ],
                ]);
            }

            if ($data['document_type'] !== 'order') {
                $billingSetting = $this->resolveBillingSetting();
                if (! $billingSetting || ! $billingSetting->enabled) {
                    throw ValidationException::withMessages([
                        'document_type' => 'La facturación electrónica está desactivada. Actívala antes de emitir boletas o facturas.',
                    ]);
                }

                [$billingSeries, $billingNumber] = $this->nextDocumentCorrelative($data['document_type'], $billingSetting);

                $createdBillingDocument = BillingDocument::query()->create([
                    'organization_id' => $organizationId,
                    'order_id' => $createdOrder->id,
                    'branch_id' => $branchId ?: null,
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
                        'address' => $data['customer']['address'] ?? null,
                        'city' => $data['customer']['city'] ?? null,
                        'phone' => $data['customer']['phone'] ?? null,
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
    private function normalizeItems(Collection $items, Collection $products, int $branchId): Collection
    {
        return $items->map(function (array $item) use ($products, $branchId): array {
            $product = $products->get((int) $item['product_id']);
            $quantity = (int) $item['quantity'];

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'items' => ["El producto con ID {$item['product_id']} no está disponible."],
                ]);
            }

            $available = $this->inventory->availableStock($product, $branchId);

            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'items' => ["Stock insuficiente para {$product->name} en la sucursal. Disponible: {$available}."],
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
            ->forCurrentOrganization()
            ->where('document_type', $documentType)
            ->where('series', $series)
            ->lockForUpdate()
            ->max('number');

        $next = ((int) $max) + 1;

        return [$series, str_pad((string) $next, 8, '0', STR_PAD_LEFT)];
    }

    private function resolveBillingSetting(): ?BillingSetting
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('billing_settings', 'organization_id')) {
            return BillingSetting::query()->first();
        }

        $organizationId = $this->organizationContext->currentOrganizationId();

        if ($organizationId) {
            return BillingSetting::query()->where('organization_id', $organizationId)->first()
                ?? BillingSetting::query()->whereNull('organization_id')->first()
                ?? BillingSetting::query()->first();
        }

        return BillingSetting::query()->whereNull('organization_id')->first()
            ?? BillingSetting::query()->first();
    }
}

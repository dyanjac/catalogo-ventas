<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
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
use Modules\Orders\Services\OrderInventoryLifecycleService;
use Modules\Orders\Services\SalesInventoryChannelRolloutService;
use Modules\Orders\Repositories\OrderRepositoryInterface;
use Modules\Sales\Services\CustomerDocumentLookupService;
use Modules\Security\Services\SecurityBranchContextService;
use Throwable;

class SalesPosController extends Controller
{
    public function __construct(
        private readonly ProductInventoryService $inventory,
        private readonly SecurityBranchContextService $branchContext,
        private readonly OrganizationContextService $organizationContext,
        private readonly SalesInventoryChannelRolloutService $channelRollouts,
        private readonly OrderInventoryLifecycleService $inventoryLifecycle,
        private readonly OrderRepositoryInterface $orders,
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
            'payment_status' => ['required', 'in:pending,paid'],
            'idempotency_key' => ['required', 'string', 'max:160'],
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
        $integrated = $this->channelRollouts->isActive((int) $organizationId, 'pos');
        $idempotencyKey = trim((string) $data['idempotency_key']);
        $payloadHash = hash('sha256', json_encode(Arr::sortRecursive([
            'organization_id' => (int) $organizationId,
            'branch_id' => $branchId,
            'document_type' => $data['document_type'],
            'currency' => $data['currency'],
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'],
            'customer' => $data['customer'],
            'items' => collect($data['items'])->map(fn (array $item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => isset($item['unit_price']) ? round((float) $item['unit_price'], 2) : null,
            ])->sortBy('product_id')->values()->all(),
            'tax_rate' => $taxRate,
            'discount' => $discount,
            'shipping' => $shipping,
        ]), JSON_THROW_ON_ERROR));

        $createdOrder = null;
        $createdBillingDocument = null;
        $payload = [];
        $replayed = false;

        try {
            DB::transaction(function () use (&$createdOrder, &$createdBillingDocument, &$payload, &$replayed, $data, $taxRate, $discount, $shipping, $branchId, $organizationId, $integrated, $idempotencyKey, $payloadHash): void {
            $existing = Order::query()
                ->where('organization_id', $organizationId)
                ->where('sales_channel', 'pos')
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con otra venta POS.']);
                }
                $createdOrder = $existing;
                $createdBillingDocument = BillingDocument::query()->where('organization_id', $organizationId)->where('order_id', $existing->id)->first();
                $replayed = true;
                return;
            }

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

            $nextOrderNumber = $this->orders->nextOrderNumber($series);
            $paidAt = $data['payment_status'] === 'paid' ? now() : null;

            $createdOrder = Order::query()->create([
                'organization_id' => $organizationId,
                'user_id' => (int) auth()->id(),
                'branch_id' => $branchId ?: null,
                'sales_channel' => 'pos',
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'series' => $series,
                'order_number' => $nextOrderNumber,
                'status' => 'confirmed',
                'warehouse_status' => $integrated ? 'reserved' : 'legacy_completed',
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
                    'organization_id' => $organizationId,
                    'order_id' => $createdOrder->id,
                    'product_id' => $line['product']->id,
                    'currency' => $data['currency'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ]);

                if (! $integrated && $line['product']->tracksInventory()) {
                    $this->inventory->decrementBranchStock($line['product'], $branchId, $line['quantity'], [
                        'reason' => 'pos_sale',
                        'performed_by' => auth()->id(),
                        'reference_type' => Order::class,
                        'reference_id' => $createdOrder->id,
                        'reference_code' => $createdOrder->series.'-'.str_pad((string) $createdOrder->order_number, 8, '0', STR_PAD_LEFT),
                        'meta' => ['channel' => 'pos', 'document_type' => $data['document_type']],
                    ]);
                }
            }

            if ($integrated) {
                $createdOrder = $this->inventoryLifecycle->reserve($createdOrder, auth()->id());
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
                    'idempotency_key' => "sales-order:{$createdOrder->id}:billing",
                    'payload_hash' => hash('sha256', json_encode([
                        'order_id' => $createdOrder->id,
                        'document_type' => $data['document_type'],
                        'total' => $total,
                    ], JSON_THROW_ON_ERROR)),
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

            if ($integrated && $data['document_type'] !== 'order') {
                $this->inventoryLifecycle->requestDispatch($createdOrder, auth()->id());
                $createdOrder->refresh();
            }
            }, max(1, (int) config('catalog.reservations.transaction_attempts', 5)));
        } catch (QueryException $exception) {
            $existing = Order::query()
                ->where('organization_id', $organizationId)
                ->where('sales_channel', 'pos')
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $existing || ! $this->isUniqueViolation($exception)) {
                throw $exception;
            }
            if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con otra venta POS.']);
            }
            $createdOrder = $existing;
            $createdBillingDocument = BillingDocument::query()->where('organization_id', $organizationId)->where('order_id', $existing->id)->first();
            $replayed = true;
        }

        if ($replayed) {
            return back()->with('success', 'La venta ya habia sido registrada; se devolvio el mismo pedido sin duplicar stock ni comprobante.');
        }

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
        $items = $items->groupBy(fn (array $item) => (int) $item['product_id'])->map(function (Collection $lines): array {
            $prices = $lines->pluck('unit_price')->filter(fn ($price) => $price !== null && $price !== '')->map(fn ($price) => round((float) $price, 2))->unique();
            if ($prices->count() > 1) {
                throw ValidationException::withMessages(['items' => 'Un producto repetido no puede usar precios unitarios diferentes.']);
            }

            return [
                'product_id' => (int) $lines->first()['product_id'],
                'quantity' => (int) $lines->sum('quantity'),
                'unit_price' => $prices->first(),
            ];
        })->values();

        return $items->map(function (array $item) use ($products, $branchId): array {
            $product = $products->get((int) $item['product_id']);
            $quantity = (int) $item['quantity'];

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'items' => ["El producto con ID {$item['product_id']} no está disponible."],
                ]);
            }

            $available = $product->tracksInventory()
                ? $this->inventory->availableStock($product, $branchId)
                : $quantity;

            if ($product->tracksInventory() && $available < $quantity) {
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
            return BillingSetting::query()->where('organization_id', $organizationId)->first();
        }

        return null;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}

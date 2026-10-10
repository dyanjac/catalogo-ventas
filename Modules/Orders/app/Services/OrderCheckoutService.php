<?php

namespace Modules\Orders\Services;

use App\Models\User;
use App\Services\DocumentTotals;
use App\Services\OrganizationContextService;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Services\ProductInventoryService;
use Modules\Orders\Entities\Order;
use Modules\Orders\Entities\OrderItem;
use Modules\Orders\Repositories\OrderRepositoryInterface;
use Modules\Security\Models\SecurityBranch;

class OrderCheckoutService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductInventoryService $inventory,
        private readonly OrganizationContextService $organizationContext,
        private readonly SalesInventoryChannelRolloutService $channelRollouts,
        private readonly OrderInventoryLifecycleService $inventoryLifecycle,
        private readonly DocumentTotals $documentTotals,
    ) {}

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $cart
     * @return array{order:Order,order_number:string}
     */
    public function checkout(array $payload, array $cart): array
    {
        $this->ensureTenantOperational();

        $series = strtoupper((string) config('orders.checkout.series', 'PED'));
        $currency = strtoupper((string) config('orders.checkout.currency', 'PEN'));
        $paymentMethod = (string) ($payload['payment_method'] ?? 'cash');
        $paymentStatus = 'pending';
        $shipping = Decimal::round((string) config('orders.checkout.shipping', 0), 2);
        $discount = Decimal::round((string) config('orders.checkout.discount', 0), 2);
        $taxRate = (string) config('orders.checkout.tax_rate', 0.18);
        $branchId = (int) (($payload['branch_id'] ?? 0)
            ?: User::query()->forCurrentOrganization()->whereKey((int) ($payload['user_id'] ?? 0))->value('branch_id')
            ?: SecurityBranch::query()->forCurrentOrganization()->where('is_default', true)->value('id')
            ?: 0);
        $organizationId = $this->organizationContext->currentOrganizationId();
        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? '')) ?: (string) Str::uuid();
        if (mb_strlen($idempotencyKey) > 160) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave idempotente admite hasta 160 caracteres.']);
        }
        $integrated = $this->channelRollouts->isActive((int) $organizationId, 'ecommerce');

        $checkoutData = $this->buildCheckoutData($cart, true, $branchId);
        $items = $checkoutData['items'];
        $totals = $this->documentTotals->calculate(
            array_map(fn (array $item): array => ['quantity' => $item['quantity'], 'unit_price' => $item['price']], $items),
            $discount, $shipping, $taxRate, (int) $organizationId, $branchId ?: null,
        );
        ['subtotal' => $subtotal, 'discount' => $discount, 'shipping' => $shipping, 'tax' => $tax, 'total' => $total] = $totals;
        $payloadHash = hash('sha256', json_encode(Arr::sortRecursive([
            'organization_id' => (int) $organizationId,
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'branch_id' => $branchId,
            'items' => collect($items)->map(fn (array $item) => [
                'id' => (int) $item['id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ])->sortBy('id')->values()->all(),
            'currency' => $currency,
            'payment_method' => $paymentMethod,
            'shipping_address' => Arr::only($payload, ['name', 'address', 'city', 'phone']),
            'totals' => compact('subtotal', 'discount', 'shipping', 'tax', 'total'),
        ]), JSON_THROW_ON_ERROR));

        /** @var array{order:Order,order_number:string}|null $result */
        $result = null;

        try {
            DB::transaction(function () use (
                $items,
                $subtotal,
                $shipping,
                $discount,
                $tax,
                $total,
                $totals,
                $series,
                $currency,
                $paymentMethod,
                $paymentStatus,
                $payload,
                $branchId,
                $organizationId,
                $idempotencyKey,
                $payloadHash,
                $integrated,
                &$result
            ): void {
                $existing = Order::query()
                    ->where('organization_id', $organizationId)
                    ->where('sales_channel', 'ecommerce')
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                        throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con otro checkout.']);
                    }
                    $result = ['order' => $existing, 'order_number' => $this->orderCode($existing)];

                    return;
                }

                $products = $this->lockProductsForCart($items);
                if (! $integrated) {
                    $branchStocks = $this->inventory->lockBranchStocksForProducts(array_keys($products->all()), $branchId);
                    $this->assertStockForItems($items, $products, $branchStocks);
                }

                $nextOrderNumber = $this->orders->nextOrderNumber($series);
                $paidAt = $paymentStatus === 'paid' ? now() : null;

                $order = $this->orders->create([
                    'organization_id' => $organizationId,
                    'user_id' => (int) ($payload['user_id'] ?? 0),
                    'branch_id' => $branchId ?: null,
                    'sales_channel' => 'ecommerce',
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'series' => $series,
                    'order_number' => $nextOrderNumber,
                    'status' => 'confirmed',
                    'warehouse_status' => $integrated ? 'reserved' : 'legacy_completed',
                    'currency' => $currency,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'shipping' => $shipping,
                    'tax' => $tax,
                    'total' => $total,
                    'shipping_address' => [
                        'name' => (string) ($payload['name'] ?? ''),
                        'address' => (string) ($payload['address'] ?? ''),
                        'city' => (string) ($payload['city'] ?? ''),
                        'phone' => (string) ($payload['phone'] ?? ''),
                    ],
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'paid_at' => $paidAt,
                    'transaction_id' => $payload['transaction_id'] ?? null,
                    'observations' => $payload['observations'] ?? null,
                ]);

                foreach ($items as $index => $item) {
                    $qty = $item['quantity'];
                    $unitPrice = $item['price'];
                    $lineTotals = $totals['lines'][$index];

                    OrderItem::query()->create([
                        'organization_id' => $organizationId,
                        'order_id' => $order->id,
                        'product_id' => (int) $item['id'],
                        'currency' => $currency,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'discount_amount' => $lineTotals['discount'],
                        'tax_amount' => $lineTotals['tax'],
                        'line_total' => $lineTotals['total'],
                    ]);

                    if (! $integrated && $products->get((string) $item['id'])->tracksInventory()) {
                        $this->inventory->decrementBranchStock($products->get((string) $item['id']), $branchId, $qty, [
                            'reason' => 'ecommerce_order',
                            'performed_by' => (int) ($payload['user_id'] ?? 0) ?: null,
                            'reference_type' => Order::class,
                            'reference_id' => $order->id,
                            'reference_code' => $this->orderCode($order),
                            'meta' => ['channel' => 'ecommerce', 'payment_method' => $paymentMethod],
                        ]);
                    }
                }

                if ($integrated) {
                    $order = $this->inventoryLifecycle->reserve($order, (int) ($payload['user_id'] ?? 0) ?: null);
                }

                $result = [
                    'order' => $order,
                    'order_number' => $this->orderCode($order),
                ];
            }, max(1, (int) config('catalog.reservations.transaction_attempts', 5)));
        } catch (QueryException $exception) {
            $existing = Order::query()
                ->where('organization_id', $organizationId)
                ->where('sales_channel', 'ecommerce')
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $existing || ! $this->isUniqueViolation($exception)) {
                throw $exception;
            }
            if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con otro checkout.']);
            }
            $result = ['order' => $existing, 'order_number' => $this->orderCode($existing)];
        }

        return $result ?? throw new \RuntimeException('No se pudo registrar el pedido.');
    }

    private function orderCode(Order $order): string
    {
        return $order->series.'-'.str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }

    /**
     * @param  array<string,mixed>  $cart
     * @return array{items:array<int,array<string,mixed>>,subtotal:string,has_issues:bool}
     */
    public function buildCheckoutData(array $cart, bool $requireStock = false, ?int $branchId = null): array
    {
        $this->ensureTenantOperational();

        $items = [];
        $hasIssues = false;

        $products = Product::query()
            ->forCurrentOrganization()
            ->with(['branchStocks' => fn ($query) => $branchId ? $query->where('branch_id', $branchId) : $query])
            ->whereKey(array_keys($cart))
            ->get()
            ->keyBy(fn (Product $product) => (string) $product->id);

        foreach ($cart as $item) {
            $product = $products->get((string) ($item['id'] ?? ''));
            $qty = Decimal::quantity($item['quantity'] ?? 1);
            if (Decimal::compare($qty, 0, 4) <= 0) {
                $hasIssues = true;

                continue;
            }

            if (! $product || ! $product->is_active) {
                $hasIssues = true;

                continue;
            }

            $available = $product->tracksInventory()
                ? $this->inventory->availableStock($product, $branchId)
                : $qty;

            if ($requireStock && $product->tracksInventory() && Decimal::compare($available, $qty, 4) < 0) {
                throw ValidationException::withMessages([
                    'cart' => ["El producto {$product->name} solo tiene {$available} unidades disponibles en la sucursal."],
                ]);
            }

            if (! $requireStock && $product->tracksInventory() && Decimal::compare($available, $qty, 4) < 0) {
                $hasIssues = true;
            }

            $items[] = [
                'id' => (string) $product->id,
                'name' => $product->name,
                'image' => $product->primary_image_path,
                'price' => Decimal::assertScale($product->display_price ?? $product->price ?? 0, 6),
                'quantity' => $qty,
                'stock' => $available,
            ];
        }

        return [
            'items' => $items,
            'subtotal' => $items === [] ? '0.00' : $this->documentTotals->calculate(
                array_map(fn (array $item): array => ['quantity' => $item['quantity'], 'unit_price' => $item['price']], $items),
                '0', '0', '0', (int) $this->organizationContext->currentOrganizationId(), $branchId,
            )['subtotal'],
            'has_issues' => $hasIssues || count($items) !== count($cart),
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     */
    private function lockProductsForCart(array $items): EloquentCollection
    {
        $productIds = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return Product::query()
            ->forCurrentOrganization()
            ->whereKey($productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Product $product) => (string) $product->id);
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @param  EloquentCollection<int,Product>  $products
     * @param  EloquentCollection<int,\Modules\Catalog\Entities\ProductBranchStock>  $branchStocks
     */
    private function assertStockForItems(array $items, EloquentCollection $products, EloquentCollection $branchStocks): void
    {
        foreach ($items as $item) {
            $product = $products->get((string) ($item['id'] ?? ''));
            $qty = Decimal::assertScale($item['quantity'] ?? 0, 4);
            $branchStock = $branchStocks->get((int) ($item['id'] ?? 0));
            $available = $branchStock?->stock ?? 0;

            if ($product && ! $product->tracksInventory()) {
                continue;
            }

            if (! $product || ! $product->is_active || Decimal::compare($available, $qty, 4) < 0) {
                throw ValidationException::withMessages([
                    'cart' => ["El producto {$item['name']} ya no tiene stock suficiente en la sucursal para completar el pedido."],
                ]);
            }
        }
    }

    private function ensureTenantOperational(): void
    {
        if (! $this->organizationContext->isSuspended()) {
            return;
        }

        throw ValidationException::withMessages([
            'cart' => 'La organización actual está suspendida y no puede procesar checkout.',
        ]);
    }
}

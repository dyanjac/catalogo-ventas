<?php

namespace Modules\Orders\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
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
        private readonly ProductInventoryService $inventory
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $cart
     * @return array{order:Order,order_number:string}
     */
    public function checkout(array $payload, array $cart): array
    {
        $series = strtoupper(trim((string) ($payload['series'] ?? 'PED')));
        if ($series === '') {
            $series = 'PED';
        }

        $currency = strtoupper(trim((string) ($payload['currency'] ?? 'PEN')));
        $paymentMethod = (string) ($payload['payment_method'] ?? 'cash');
        $paymentStatus = (string) ($payload['payment_status'] ?? 'pending');
        $shipping = round((float) ($payload['shipping'] ?? 0), 2);
        $discount = round((float) ($payload['discount'] ?? 0), 2);
        $taxRate = (float) ($payload['tax_rate'] ?? 0.18);
        $branchId = (int) (($payload['branch_id'] ?? 0) ?: User::query()->whereKey((int) ($payload['user_id'] ?? 0))->value('branch_id') ?: SecurityBranch::query()->where('is_default', true)->value('id') ?: 0);

        $checkoutData = $this->buildCheckoutData($cart, true, $branchId);
        $items = $checkoutData['items'];
        $subtotal = $checkoutData['subtotal'];
        $discount = min($discount, $subtotal);
        $taxableBase = max(0, $subtotal - $discount);
        $tax = round($taxableBase * $taxRate, 2);
        $total = round($taxableBase + $shipping + $tax, 2);

        /** @var array{order:Order,order_number:string}|null $result */
        $result = null;

        DB::transaction(function () use (
            $items,
            $subtotal,
            $shipping,
            $discount,
            $tax,
            $total,
            $series,
            $currency,
            $paymentMethod,
            $paymentStatus,
            $payload,
            $branchId,
            &$result
        ): void {
            $products = $this->lockProductsForCart($items);
            $branchStocks = $this->inventory->lockBranchStocksForProducts(array_keys($products->all()), $branchId);
            $this->assertStockForItems($items, $products, $branchStocks);

            $nextOrderNumber = $this->orders->nextOrderNumber($series);
            $paidAt = $paymentStatus === 'paid' ? now() : null;

            $order = $this->orders->create([
                'user_id' => (int) ($payload['user_id'] ?? 0),
                'branch_id' => $branchId ?: null,
                'series' => $series,
                'order_number' => $nextOrderNumber,
                'status' => 'confirmed',
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

            $discountRatio = $subtotal > 0 ? ($discount / $subtotal) : 0;
            $taxable = max(0, $subtotal - $discount);
            $taxRatio = $taxable > 0 ? ($tax / $taxable) : 0;

            foreach ($items as $item) {
                $qty = (int) $item['quantity'];
                $unitPrice = (float) $item['price'];
                $lineSubtotal = round($qty * $unitPrice, 2);
                $lineDiscount = round($lineSubtotal * $discountRatio, 2);
                $lineTaxable = max(0, $lineSubtotal - $lineDiscount);
                $lineTax = round($lineTaxable * $taxRatio, 2);
                $lineTotal = round($lineTaxable + $lineTax, 2);

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => (int) $item['id'],
                    'currency' => $currency,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'line_total' => $lineTotal,
                ]);

                $this->inventory->decrementBranchStock($products->get((string) $item['id']), $branchId, $qty, [
                    'reason' => 'ecommerce_order',
                    'performed_by' => (int) ($payload['user_id'] ?? 0) ?: null,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'reference_code' => $order->series.'-'.str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT),
                    'meta' => [
                        'channel' => 'ecommerce',
                        'payment_method' => $paymentMethod,
                    ],
                ]);
            }

            $result = [
                'order' => $order,
                'order_number' => $order->series.'-'.str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT),
            ];
        });

        return $result ?? throw new \RuntimeException('No se pudo registrar el pedido.');
    }

    /**
     * @param array<string,mixed> $cart
     * @return array{items:array<int,array<string,mixed>>,subtotal:float,has_issues:bool}
     */
    public function buildCheckoutData(array $cart, bool $requireStock = false, ?int $branchId = null): array
    {
        $items = [];
        $hasIssues = false;

        $products = Product::query()
            ->with(['branchStocks' => fn ($query) => $branchId ? $query->where('branch_id', $branchId) : $query])
            ->whereKey(array_keys($cart))
            ->get()
            ->keyBy(fn (Product $product) => (string) $product->id);

        foreach ($cart as $item) {
            $product = $products->get((string) ($item['id'] ?? ''));
            $qty = max(1, (int) ($item['quantity'] ?? 0));

            if (! $product || ! $product->is_active) {
                $hasIssues = true;
                continue;
            }

            $available = $this->inventory->availableStock($product, $branchId);

            if ($requireStock && $available < $qty) {
                throw ValidationException::withMessages([
                    'cart' => ["El producto {$product->name} solo tiene {$available} unidades disponibles en la sucursal."],
                ]);
            }

            if (! $requireStock && $available < $qty) {
                $hasIssues = true;
            }

            $items[] = [
                'id' => (string) $product->id,
                'name' => $product->name,
                'image' => $product->primary_image_path,
                'price' => (float) ($product->display_price ?? $product->price ?? 0),
                'quantity' => $qty,
                'stock' => $available,
            ];
        }

        return [
            'items' => $items,
            'subtotal' => round((float) collect($items)->sum(fn ($item) => $item['quantity'] * $item['price']), 2),
            'has_issues' => $hasIssues || count($items) !== count($cart),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private function lockProductsForCart(array $items): EloquentCollection
    {
        $productIds = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return Product::query()
            ->whereKey($productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Product $product) => (string) $product->id);
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param EloquentCollection<int,Product> $products
     * @param EloquentCollection<int,\Modules\Catalog\Entities\ProductBranchStock> $branchStocks
     */
    private function assertStockForItems(array $items, EloquentCollection $products, EloquentCollection $branchStocks): void
    {
        foreach ($items as $item) {
            $product = $products->get((string) ($item['id'] ?? ''));
            $qty = (int) ($item['quantity'] ?? 0);
            $branchStock = $branchStocks->get((int) ($item['id'] ?? 0));
            $available = (int) ($branchStock?->stock ?? 0);

            if (! $product || ! $product->is_active || $available < $qty) {
                throw ValidationException::withMessages([
                    'cart' => ["El producto {$item['name']} ya no tiene stock suficiente en la sucursal para completar el pedido."],
                ]);
            }
        }
    }
}


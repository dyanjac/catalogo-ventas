<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Decimal;
use InvalidArgumentException;

final class DocumentTotals
{
    public function __construct(private readonly RoundingPolicy $rounding) {}

    /**
     * @param  list<array{quantity:int|float|string,unit_price:int|float|string}>  $items
     * @return array{subtotal:string,discount:string,shipping:string,tax:string,total:string,lines:list<array{subtotal:string,discount:string,tax:string,total:string}>}
     */
    public function calculate(array $items, int|float|string $discount, int|float|string $shipping, int|float|string $taxRate, int $organizationId, ?int $branchId = null): array
    {
        $mode = $this->rounding->mode($organizationId, $branchId);
        $discount = Decimal::assertScale($discount, 2);
        $shipping = Decimal::assertScale($shipping, 2);
        $taxRate = Decimal::normalize($taxRate);

        if (Decimal::compare($discount, 0) < 0 || Decimal::compare($shipping, 0) < 0 || Decimal::compare($taxRate, 0) < 0) {
            throw new InvalidArgumentException('Los importes y la tasa de impuesto no pueden ser negativos.');
        }

        $lines = [];
        $subtotal = '0';
        foreach ($items as $item) {
            $quantity = Decimal::assertScale($item['quantity'], 4);
            $unitPrice = Decimal::assertScale($item['unit_price'], 6);
            if (Decimal::compare($quantity, 0) <= 0 || Decimal::compare($unitPrice, 0) < 0) {
                throw new InvalidArgumentException('Cantidad o precio unitario invalido.');
            }
            $lineSubtotal = Decimal::round(Decimal::mul($quantity, $unitPrice, 10), 2, $mode);
            $subtotal = Decimal::add($subtotal, $lineSubtotal, 2);
            $lines[] = ['subtotal' => $lineSubtotal, 'discount' => '0.00', 'tax' => '0.00', 'total' => '0.00'];
        }

        if ($lines === []) {
            throw new InvalidArgumentException('El documento necesita al menos una linea.');
        }

        $discount = Decimal::compare($discount, $subtotal) > 0 ? $subtotal : $discount;
        $remainders = [];
        $remaining = $discount;
        foreach ($lines as $index => &$line) {
            $raw = Decimal::compare($subtotal, 0) === 0
                ? '0'
                : Decimal::div(Decimal::mul($discount, $line['subtotal'], 12), $subtotal, 12);
            $line['discount'] = bcadd($raw, '0', 2);
            $remainders[$index] = Decimal::sub($raw, $line['discount'], 12);
            $remaining = Decimal::sub($remaining, $line['discount'], 2);
        }
        unset($line);

        uksort($remainders, fn (int $left, int $right): int => Decimal::compare($remainders[$right], $remainders[$left], 12) ?: $left <=> $right);
        while (Decimal::compare($remaining, 0, 2) > 0) {
            $allocated = false;
            foreach (array_keys($remainders) as $index) {
                if (Decimal::compare(Decimal::sub($lines[$index]['subtotal'], $lines[$index]['discount'], 2), '0.01', 2) < 0) {
                    continue;
                }
                $lines[$index]['discount'] = Decimal::add($lines[$index]['discount'], '0.01', 2);
                $remaining = Decimal::sub($remaining, '0.01', 2);
                $allocated = true;
                if (Decimal::compare($remaining, 0, 2) === 0) {
                    break;
                }
            }
            if (! $allocated) {
                throw new InvalidArgumentException('No se pudo distribuir el descuento entre las lineas.');
            }
        }

        $tax = '0';
        foreach ($lines as &$line) {
            $line['discount'] = Decimal::round($line['discount'], 2, $mode);
            $base = Decimal::sub($line['subtotal'], $line['discount'], 2);
            $line['tax'] = Decimal::round(Decimal::mul($base, $taxRate, 12), 2, $mode);
            $line['total'] = Decimal::round(Decimal::add($base, $line['tax'], 2), 2, $mode);
            $tax = Decimal::add($tax, $line['tax'], 2);
        }
        unset($line);

        $subtotal = Decimal::round($subtotal, 2, $mode);
        $discount = Decimal::round($discount, 2, $mode);
        $shipping = Decimal::round($shipping, 2, $mode);
        $tax = Decimal::round($tax, 2, $mode);
        $total = Decimal::round(Decimal::add(Decimal::sub($subtotal, $discount, 2), Decimal::add($tax, $shipping, 2), 2), 2, $mode);

        return compact('subtotal', 'discount', 'shipping', 'tax', 'total', 'lines');
    }
}

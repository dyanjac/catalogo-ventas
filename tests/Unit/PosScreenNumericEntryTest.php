<?php

namespace Tests\Unit;

use App\Livewire\Admin\PosScreen;
use PHPUnit\Framework\TestCase;

class PosScreenNumericEntryTest extends TestCase
{
    public function test_product_selection_fills_the_price_without_rewriting_typed_decimals(): void
    {
        $screen = new PosScreen;
        $screen->productIndex = [
            ['id' => 7, 'name' => 'Producto', 'sku' => 'SKU-7', 'label' => 'Producto (SKU-7)', 'price' => 12.5],
        ];
        $screen->addItem();

        $this->assertSame('1', $screen->items[0]['quantity']);
        $this->assertSame('', $screen->items[0]['unit_price']);

        $screen->items[0]['product_id'] = '7';
        $screen->updatedItems(null, '0.product_id');
        $this->assertSame('12.50', $screen->items[0]['unit_price']);

        $screen->items[0]['unit_price'] = '0.';
        $screen->updatedItems('0.', '0.unit_price');
        $this->assertSame('0.', $screen->items[0]['unit_price']);

        $screen->items[0]['quantity'] = '0.5';
        $screen->items[0]['unit_price'] = '12.50';
        $this->assertSame(6.25, $screen->subtotal());
        $this->assertSame(0.5, $screen->itemCount());
    }
}

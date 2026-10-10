<?php

namespace Tests\Unit;

use App\Livewire\Admin\PosScreen;
use PHPUnit\Framework\TestCase;

class PosScreenNumericEntryTest extends TestCase
{
    public function test_product_suggestions_and_advanced_filters_keep_stock_and_brand_distinct(): void
    {
        $screen = new PosScreen;
        $screen->productIndex = [
            ['id' => 1, 'name' => 'Café molido', 'sku' => 'CAF-01', 'brand' => 'Andes', 'description' => 'Tueste oscuro', 'category_id' => 2, 'stock' => 7, 'price' => 18.5, 'tracks_inventory' => true],
            ['id' => 2, 'name' => 'Café premium', 'sku' => 'CAF-02', 'brand' => 'Costa', 'description' => 'Tueste claro', 'category_id' => 2, 'stock' => 0, 'price' => 25, 'tracks_inventory' => true],
            ['id' => 3, 'name' => 'Té verde', 'sku' => 'TE-01', 'brand' => 'Andes', 'description' => 'Hojas enteras', 'category_id' => 3, 'stock' => 4, 'price' => 12, 'tracks_inventory' => true],
        ];
        $screen->productSearch = 'CAF';

        $this->assertSame([1, 2], array_column($screen->productSuggestions(), 'id'));

        $screen->advancedFilters = [
            'term' => '', 'category_id' => '2', 'stock' => 'available',
            'min_price' => '15', 'max_price' => '20', 'brand' => 'andes', 'sku' => 'CAF', 'description' => 'oscuro',
        ];
        $this->assertSame([1], array_column($screen->advancedResults(), 'id'));

        $screen->advancedFilters['stock'] = 'out';
        $screen->advancedFilters['brand'] = '';
        $screen->advancedFilters['max_price'] = '30';
        $screen->advancedFilters['description'] = '';
        $this->assertSame([2], array_column($screen->advancedResults(), 'id'));
    }

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
        $this->assertSame('6.25', $screen->subtotal());
        $this->assertSame('0.5', $screen->itemCount());
    }
}

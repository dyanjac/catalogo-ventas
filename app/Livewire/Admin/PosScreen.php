<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Modules\Catalog\Entities\Product;

class PosScreen extends Component
{
    public function render()
    {
        return view('livewire.admin.pos-screen', [
            'products' => Product::query()
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'sale_price', 'price', 'stock']),
            'defaultTaxRate' => (float) config('sales.default_tax_rate', 0.18),
            'defaultCurrency' => config('sales.default_currency', 'PEN'),
        ]);
    }
}

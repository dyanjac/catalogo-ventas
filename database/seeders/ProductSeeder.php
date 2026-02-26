<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\UnitMeasure;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Category::count() === 0 || UnitMeasure::count() === 0) {
            return;
        }

        Product::factory(24)->create();
    }
}

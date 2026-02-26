<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\UnitMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $salePrice = fake()->randomFloat(2, 5, 500);

        return [
            'category_id' => Category::inRandomOrder()->value('id'),
            'unit_measure_id' => UnitMeasure::inRandomOrder()->value('id'),
            'name'        => $name,
            'sku'         => 'PRD-' . Str::upper(Str::random(8)),
            'slug'        => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'description' => fake()->paragraph(),
            'tax_affectation' => fake()->randomElement(['Gravado', 'Exonerado', 'Inafecto']),
            'uses_series' => fake()->boolean(20),
            'account' => fake()->optional()->numerify('70####'),
            'purchase_price' => fake()->optional()->randomFloat(2, 1, 350),
            'sale_price' => $salePrice,
            'wholesale_price' => fake()->optional()->randomFloat(2, 1, 420),
            'average_price' => fake()->optional()->randomFloat(2, 1, 400),
            'price' => $salePrice,
            'stock'       => fake()->numberBetween(0, 200),
            'min_stock' => fake()->numberBetween(0, 20),
            'image'       => null,
            'is_active'   => true,
        ];
    }
}

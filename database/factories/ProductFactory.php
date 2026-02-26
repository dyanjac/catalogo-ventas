<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category;
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

        return [
            'category_id' => Category::inRandomOrder()->value('id') ?? Category::factory(),
            'name'        => $name,
            'slug'        => Str::slug($name) . '-' . Str::lower(Str::random(5)),
            'description' => fake()->paragraph(),
            'price'       => fake()->randomFloat(2, 5, 500),
            'stock'       => fake()->numberBetween(0, 200),
            'image'       => null,
            'is_active'   => true,
        ];
    }
}

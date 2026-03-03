<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\UnitMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_complete_checkout(): void
    {
        $user = User::factory()->create([
            'phone' => '999888777',
            'city' => 'Lima',
            'address' => 'Av. Principal 123',
        ]);

        $product = $this->createProduct([
            'name' => 'Harina Premium',
            'price' => 12.50,
            'sale_price' => 12.50,
            'stock' => 10,
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'cart' => [
                    (string) $product->id => [
                        'id' => (string) $product->id,
                        'name' => $product->name,
                        'price' => 10,
                        'quantity' => 2,
                        'image' => null,
                    ],
                ],
            ])
            ->post(route('checkout.store'), [
                'name' => 'Cliente Demo',
                'address' => 'Jr. Comercio 456',
                'city' => 'Lima',
                'phone' => '987654321',
                'series' => 'PED',
                'currency' => 'PEN',
                'discount' => 2,
                'shipping' => 5,
                'tax_rate' => 0.18,
                'payment_method' => 'transfer',
                'payment_status' => 'paid',
                'transaction_id' => 'TRX-001',
                'observations' => 'Entregar por la tarde',
            ]);

        $response->assertRedirect(route('orders.mine'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'series' => 'PED',
            'currency' => 'PEN',
            'payment_method' => 'transfer',
            'payment_status' => 'paid',
            'transaction_id' => 'TRX-001',
        ]);

        $order = Order::query()->with('items')->firstOrFail();

        $this->assertSame('Cliente Demo', $order->shipping_address['name']);
        $this->assertEquals(25.00, (float) $order->subtotal);
        $this->assertEquals(2.00, (float) $order->discount);
        $this->assertEquals(5.00, (float) $order->shipping);
        $this->assertEquals(4.14, (float) $order->tax);
        $this->assertEquals(32.14, (float) $order->total);
        $this->assertCount(1, $order->items);
        $this->assertEquals(8, $product->fresh()->stock);
    }

    public function test_checkout_fails_when_product_stock_is_insufficient(): void
    {
        $user = User::factory()->create();

        $product = $this->createProduct([
            'name' => 'Manteca Industrial',
            'price' => 18.00,
            'sale_price' => 18.00,
            'stock' => 1,
        ]);

        $response = $this->from(route('checkout.show'))
            ->actingAs($user)
            ->withSession([
                'cart' => [
                    (string) $product->id => [
                        'id' => (string) $product->id,
                        'name' => $product->name,
                        'price' => 18.00,
                        'quantity' => 3,
                        'image' => null,
                    ],
                ],
            ])
            ->post(route('checkout.store'), [
                'name' => 'Cliente Demo',
                'address' => 'Jr. Comercio 456',
                'city' => 'Lima',
                'phone' => '987654321',
            ]);

        $response->assertRedirect(route('checkout.show'));
        $response->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(1, $product->fresh()->stock);
    }

    private function createProduct(array $attributes = []): Product
    {
        $category = Category::query()->create([
            'name' => 'Categoria ' . uniqid(),
            'slug' => 'categoria-' . uniqid(),
        ]);

        $unitMeasure = UnitMeasure::query()->create([
            'name' => 'Unidad ' . uniqid(),
        ]);

        return Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'unit_measure_id' => $unitMeasure->id,
        ], $attributes));
    }
}

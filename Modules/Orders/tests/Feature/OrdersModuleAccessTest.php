<?php

namespace Modules\Orders\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Orders\Entities\Order;
use Tests\TestCase;

class OrdersModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_my_orders_route(): void
    {
        $response = $this->get(route('orders.mine'));

        $response->assertRedirect('/login');
    }

    public function test_customer_can_see_own_order_detail_and_cannot_see_other_order(): void
    {
        $owner = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
        $other = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);

        $ownOrder = Order::query()->create([
            'user_id' => $owner->id,
            'series' => 'PED',
            'order_number' => 1,
            'status' => 'pending',
            'currency' => 'PEN',
            'subtotal' => 10,
            'discount' => 0,
            'shipping' => 0,
            'tax' => 1.8,
            'total' => 11.8,
            'shipping_address' => [
                'name' => 'Cliente Uno',
                'address' => 'Dir 123',
                'city' => 'Lima',
                'phone' => '999888777',
            ],
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $otherOrder = Order::query()->create([
            'user_id' => $other->id,
            'series' => 'PED',
            'order_number' => 2,
            'status' => 'pending',
            'currency' => 'PEN',
            'subtotal' => 10,
            'discount' => 0,
            'shipping' => 0,
            'tax' => 1.8,
            'total' => 11.8,
            'shipping_address' => [
                'name' => 'Cliente Dos',
                'address' => 'Dir 456',
                'city' => 'Lima',
                'phone' => '988777666',
            ],
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->get(route('orders.show', $ownOrder))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('orders.show', $otherOrder))
            ->assertForbidden();
    }
}

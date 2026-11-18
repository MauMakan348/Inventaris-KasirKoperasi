<?php

namespace Database\Factories;

use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAmount = fake()->numberBetween(5000, 500000);

        return [
            'order_code' => fake()->unique()->bothify('ORD-########'),
            'user_id' => User::factory(),
            'channel' => OrderChannel::Pos,
            'status' => 'COMPLETED',
            'payment_method' => PaymentMethod::Cash,
            'total_amount' => $totalAmount,
            'amount_paid' => $totalAmount,
            'change_amount' => 0,
        ];
    }

    /**
     * Indicate that the order was placed online by a member.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => OrderChannel::Online,
            'payment_method' => PaymentMethod::Transfer,
            'amount_paid' => null,
            'change_amount' => null,
        ]);
    }
}

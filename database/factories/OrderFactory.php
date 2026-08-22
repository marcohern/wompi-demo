<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
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
        return [
            'user_id' => User::factory(),
            'reference' => Order::newReference(),
            'amount_in_cents' => fake()->numberBetween(20_000, 50_000_000),
            'currency' => 'COP',
            'status' => OrderStatus::Pending,
            'wompi_transaction_id' => null,
            'paid_at' => null,
        ];
    }

    /**
     * Indicate that the order was paid successfully.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Approved,
            'wompi_transaction_id' => fake()->uuid(),
            'paid_at' => now(),
        ]);
    }
}

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
            'reference' => Order::placeholderReference(),
            'amount_in_cents' => fake()->numberBetween(20_000, 50_000_000),
            'currency' => 'COP',
            'status' => OrderStatus::Pending,
            'wompi_transaction_id' => null,
            'paid_at' => null,
        ];
    }

    /**
     * Give the order its id-bearing reference, mirroring the checkout flow.
     *
     * An explicitly supplied reference is left alone so tests can pin one.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            if (str_starts_with($order->reference, Order::PLACEHOLDER_PREFIX)) {
                $order->assignReference();
            }
        });
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

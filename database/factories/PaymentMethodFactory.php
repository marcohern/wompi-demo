<?php

namespace Database\Factories;

use App\Enums\CardBrand;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * Published, non-functional test card numbers.
     *
     * @var array<string, string>
     */
    private const TEST_NUMBERS = [
        CardBrand::Visa->value => '4242424242424242',
        CardBrand::Mastercard->value => '5555555555554444',
        CardBrand::Amex->value => '378282246310005',
        CardBrand::Discover->value => '6011111111111117',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->randomElement(self::TEST_NUMBERS);

        return [
            'user_id' => User::factory(),
            'brand' => CardBrand::detect($number),
            'last_four' => substr($number, -4),
            'number' => $number,
            'holder_name' => fake()->name(),
            'expiry_month' => fake()->numberBetween(1, 12),
            'expiry_year' => (int) date('Y') + fake()->numberBetween(1, 5),
            'is_default' => false,
        ];
    }

    /**
     * Keep the derived columns consistent with the number.
     *
     * Without this, a test overriding `number` would keep the randomly
     * chosen brand and last four digits from the definition above.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (PaymentMethod $method): void {
            $method->brand = CardBrand::detect($method->number);
            $method->last_four = substr($method->number, -4);
        });
    }

    /**
     * Indicate that this is the user's default payment method.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the card has already expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_month' => 1,
            'expiry_year' => (int) date('Y') - 1,
        ]);
    }
}

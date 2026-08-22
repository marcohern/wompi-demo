<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var array<string, string> $countries */
        $countries = config('countries');

        return [
            'user_id' => User::factory(),
            'country' => fake()->randomElement(array_keys($countries)),
            // Faker's state() only exists in the en_US provider, so a
            // locale independent sample is used instead.
            'political_region' => fake()->randomElement([
                'Antioquia',
                'Cundinamarca',
                'Valle del Cauca',
                'California',
                'Ontario',
                'Bavaria',
            ]),
            'city' => fake()->city(),
            'address1' => fake()->streetAddress(),
            'address2' => null,
            'zip_code' => fake()->postcode(),
            'notes' => null,
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the address is the user's default delivery address.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}

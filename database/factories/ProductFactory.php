<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
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
        return [
            'title' => Str::title(implode(' ', [
                fake()->word(),
                fake()->word(),
                fake()->word(),
            ])),
            'price' => fake()->randomFloat(2, 5, 500),
            'image_url' => sprintf(
                'https://picsum.photos/seed/%d/400/300',
                fake()->unique()->numberBetween(1, 1000),
            ),
        ];
    }
}

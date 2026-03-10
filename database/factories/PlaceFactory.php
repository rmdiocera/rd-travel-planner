<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Place>
 */
class PlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'details' => fake()->paragraph(),
            'address' => fake()->streetAddress(),
            'country' => fake()->country(),
            'city' => fake()->city(),
            'website' => fake()->optional()->url(),
            'phone' => fake()->optional()->phoneNumber(),
            'image' => fake()->optional()->imageUrl(),
        ];
    }
}

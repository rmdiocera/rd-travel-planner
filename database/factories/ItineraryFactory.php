<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Itinerary>
 */
class ItineraryFactory extends Factory
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
            'name' => 'Trip to '.fake()->randomElement([fake()->country(), fake()->city()]),
            'start_date' => fake()->optional()->dateTimeBetween('+1 week', '+1 month'),
            'end_date' => fake()->optional()->dateTimeBetween('+1 month', '+2 months'),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Itinerary;
use App\Models\ItinerarySpot;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItinerarySpot>
 */
class ItinerarySpotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'itinerary_id' => Itinerary::factory(),
            'place_id' => Place::factory(),
            'visit_date' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'start_time' => null,
            'end_time' => null,
            'marked_visited' => false,
            'sort_order' => 1,
        ];
    }
}

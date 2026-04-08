<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItineraryListItemType;
use App\Models\ItineraryList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ItineraryListItem>
 */
class ItineraryListItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'itinerary_lists_id' => ItineraryList::factory(),
            'type' => fake()->randomElement(ItineraryListItemType::cases())->value,
            'content' => null,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}

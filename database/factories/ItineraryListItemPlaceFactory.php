<?php

namespace Database\Factories;

use App\Models\ItineraryListItem;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ItineraryListItemPlace>
 */
class ItineraryListItemPlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'itinerary_list_item_id' => ItineraryListItem::factory(),
            'place_id' => Place::factory(),
        ];
    }
}

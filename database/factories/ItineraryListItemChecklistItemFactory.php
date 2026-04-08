<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ItineraryListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ItineraryListItemChecklistItem>
 */
class ItineraryListItemChecklistItemFactory extends Factory
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
            'label' => fake()->words(3, true),
            'is_checked' => false,
            'sort_order' => 1,
        ];
    }
}

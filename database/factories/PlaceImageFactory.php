<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Place;
use App\Models\PlaceImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlaceImage>
 */
class PlaceImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'place_id' => Place::factory(),
            'path' => 'places/'.Str::random(40).'.jpg',
        ];
    }
}

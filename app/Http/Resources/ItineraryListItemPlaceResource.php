<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItineraryListItemPlaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'itinerary_list_item_id' => $this->itinerary_list_item_id,
            'place_id' => $this->place_id,
            'place' => new PlaceResource($this->whenLoaded('place')),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'marked_visited' => $this->marked_visited,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

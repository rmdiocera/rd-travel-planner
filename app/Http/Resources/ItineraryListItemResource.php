<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItineraryListItemResource extends JsonResource
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
            'itinerary_lists_id' => $this->itinerary_lists_id,
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'sort_order' => $this->sort_order,
            'place' => new ItineraryListItemPlaceResource($this->whenLoaded('placeItem')),
            'checklist_items' => ItineraryListItemChecklistItemResource::collection($this->whenLoaded('checklistItems')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

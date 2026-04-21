<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItineraryListItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreItineraryListItemRequest;
use App\Http\Requests\Api\V1\UpdateItineraryListItemRequest;
use App\Http\Resources\ItineraryListItemResource;
use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItineraryListItemController extends Controller
{
    /**
     * Store a newly created item in the given list.
     */
    public function store(StoreItineraryListItemRequest $request, Itinerary $itinerary, ItineraryList $list): JsonResponse
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $item = $list->items()->create($request->validated() + [
            'sort_order' => $list->items()->max('sort_order') + 1,
        ]);

        if ($item->type === ItineraryListItemType::Place) {
            $item->placeItem()->create([
                'place_id' => $request->validated()['place_id'],
            ]);
            $item->load('placeItem.place');
        }

        return (new ItineraryListItemResource($item))->response()->setStatusCode(201);
    }

    /**
     * Update the specified item.
     */
    public function update(UpdateItineraryListItemRequest $request, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): ItineraryListItemResource
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        match ($item->type) {
            ItineraryListItemType::Place => $this->updatePlace($item, $request),
            ItineraryListItemType::Checklist => $this->updateChecklist($item, $request),
            ItineraryListItemType::Note => $this->updateNote($item, $request),
        };

        return new ItineraryListItemResource($item);
    }

    /**
     * Reorder the list items.
     */
    public function reorderListItems(Request $request, Itinerary $itinerary, ItineraryList $list): JsonResponse
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'list_item_ids' => ['required', 'array'],
            'list_item_ids.*' => ['string', 'exists:itinerary_list_items,id'],
        ]);

        $list->reorderListItems($validated['list_item_ids']);

        return response()->json(null, 204);
    }

    /**
     * Remove the specified item.
     */
    public function destroy(Request $request, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): JsonResponse
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);
        abort_if($item->itinerary_lists_id !== $list->id, 404);

        $item->delete();

        if ($list->items()->count() > 0) {
            $list->reorderListItems($list->items()
                ->orderBy('sort_order')
                ->pluck('id')
                ->toArray()
            );
        }

        return response()->json(null, 204);
    }

    private function updatePlace(ItineraryListItem $item, UpdateItineraryListItemRequest $request): void
    {
        $item->placeItem()->update($request->safe()->only(['start_time', 'end_time', 'marked_visited']));
        $item->load('placeItem.place');
    }

    private function updateChecklist(ItineraryListItem $item, UpdateItineraryListItemRequest $request): void
    {
        $item->update($request->safe()->only(['title']));
    }

    private function updateNote(ItineraryListItem $item, UpdateItineraryListItemRequest $request): void
    {
        $item->update($request->safe()->only(['content']));
    }
}

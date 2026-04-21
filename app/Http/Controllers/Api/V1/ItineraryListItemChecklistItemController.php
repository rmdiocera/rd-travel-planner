<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreItineraryListItemChecklistItemRequest;
use App\Http\Requests\Api\V1\UpdateItineraryListItemChecklistItemRequest;
use App\Http\Resources\ItineraryListItemChecklistItemResource;
use App\Models\Itinerary;
use App\Models\ItineraryList;
use App\Models\ItineraryListItem;
use App\Models\ItineraryListItemChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItineraryListItemChecklistItemController extends Controller
{
    /**
     * Store a newly created checklist item.
     */
    public function store(StoreItineraryListItemChecklistItemRequest $request, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): JsonResponse
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $checklist_item = $item->checklistItems()->create([
            ...$request->validated(),
            'sort_order' => $item->checklistItems()->max('sort_order') + 1,
        ]);

        return (new ItineraryListItemChecklistItemResource($checklist_item))->response()->setStatusCode(201);
    }

    /**
     * Update the specified checklist item.
     */
    public function update(UpdateItineraryListItemChecklistItemRequest $request, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item, ItineraryListItemChecklistItem $checklistItem): ItineraryListItemChecklistItemResource
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $checklistItem->update($request->validated());

        return new ItineraryListItemChecklistItemResource($checklistItem);
    }

    /**
     * Reorder the checklist items.
     */
    public function reorder(Request $request, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): JsonResponse
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'checklist_item_ids' => ['required', 'array'],
            'checklist_item_ids.*' => ['string', 'exists:itinerary_list_item_checklist_items,id'],
        ]);

        $item->reorderChecklistItems($validated['checklist_item_ids']);

        return response()->json(null, 204);
    }

    /**
     * Remove the specified checklist item.
     */
    public function destroy(Request $request, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item, ItineraryListItemChecklistItem $checklistItem): JsonResponse
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $checklistItem->delete();

        if ($item->checklistItems()->count() > 0) {
            $item->reorderChecklistItems($item->checklistItems()
                ->orderBy('sort_order')
                ->pluck('id')
                ->toArray()
            );
        }

        return response()->json(null, 204);
    }
}

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
use Illuminate\Support\Facades\Gate;

class ItineraryListItemChecklistItemController extends Controller
{
    /**
     * Store a newly created checklist item.
     */
    public function store(StoreItineraryListItemChecklistItemRequest $request, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): JsonResponse
    {
        Gate::authorize('create', [ItineraryListItemChecklistItem::class, $itinerary, $list, $item]);

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
        Gate::authorize('update', [$checklistItem, $itinerary, $list, $item]);

        $checklistItem->update($request->validated());

        return new ItineraryListItemChecklistItemResource($checklistItem);
    }

    /**
     * Reorder the checklist items.
     */
    public function reorder(Request $request, Itinerary $itinerary, ItineraryList $list, ItineraryListItem $item): JsonResponse
    {
        Gate::authorize('reorder', [ItineraryListItemChecklistItem::class, $itinerary, $list, $item]);

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
        Gate::authorize('delete', [$checklistItem, $itinerary, $list, $item]);

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

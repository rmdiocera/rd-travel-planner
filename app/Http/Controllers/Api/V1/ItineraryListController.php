<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreItineraryListRequest;
use App\Http\Requests\Api\V1\UpdateItineraryListRequest;
use App\Http\Resources\ItineraryListResource;
use App\Models\Itinerary;
use App\Models\ItineraryList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ItineraryListController extends Controller
{
    /**
     * Return a listing of the itinerary's lists.
     */
    public function index(Request $request, Itinerary $itinerary): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [ItineraryList::class, $itinerary]);

        return ItineraryListResource::collection(
            $itinerary->lists()
                ->with([
                    'items' => function ($query) {
                        $query->orderBy('sort_order');
                    },
                    'items.placeItem.place',
                    'items.checklistItems' => function ($query) {
                        $query->orderBy('sort_order');
                    },
                ])
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Store a newly created list.
     */
    public function store(StoreItineraryListRequest $request, Itinerary $itinerary): JsonResponse
    {
        Gate::authorize('viewAny', [ItineraryList::class, $itinerary]);

        $list = $itinerary->lists()->create($request->validated() + [
            'sort_order' => $itinerary->lists()->max('sort_order') + 1,
        ]);

        return (new ItineraryListResource($list))->response()->setStatusCode(201);
    }

    /**
     * Update the specified list.
     */
    public function update(UpdateItineraryListRequest $request, Itinerary $itinerary, ItineraryList $list): ItineraryListResource
    {
        Gate::authorize('update', [$list, $itinerary]);

        $list->update($request->validated());

        return new ItineraryListResource($list);
    }

    public function reorder(Request $request, Itinerary $itinerary): JsonResponse
    {
        Gate::authorize('reorder', [ItineraryList::class, $itinerary]);

        $validated = $request->validate([
            'list_ids' => ['required', 'array'],
            'list_ids.*' => ['string', 'exists:itinerary_lists,id'],
        ]);

        $itinerary->reorderLists($validated['list_ids']);

        return response()->json(null, 204);
    }

    /**
     * Remove the specified list.
     */
    public function destroy(Request $request, Itinerary $itinerary, ItineraryList $list): JsonResponse
    {
        Gate::authorize('delete', [$list, $itinerary]);

        $list->delete();

        if ($itinerary->lists()->count() > 0) {
            $itinerary->reorderLists($itinerary->lists()
                ->orderBy('sort_order')
                ->pluck('id')
                ->toArray()
            );
        }

        return response()->json(null, 204);
    }
}

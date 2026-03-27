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

class ItineraryListController extends Controller
{
    /**
     * Return a listing of the itinerary's lists.
     */
    public function index(Request $request, Itinerary $itinerary): AnonymousResourceCollection
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        return ItineraryListResource::collection(
            $itinerary->lists()->orderBy('sort_order')->get()
        );
    }

    /**
     * Store a newly created list.
     */
    public function store(StoreItineraryListRequest $request, Itinerary $itinerary): JsonResponse
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

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
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $list->update($request->validated());

        return new ItineraryListResource($list);
    }

    public function reorder(Request $request, Itinerary $itinerary): JsonResponse
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

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
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $list->delete();

        if ($itinerary->lists()->count() > 0) {
            $itinerary->reorderLists($itinerary->lists()->pluck('id')->toArray());
        }

        return response()->json(null, 204);
    }
}

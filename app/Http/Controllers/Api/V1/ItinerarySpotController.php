<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReorderItinerarySpotsRequest;
use App\Http\Requests\Api\V1\StoreItinerarySpotRequest;
use App\Http\Requests\Api\V1\UpdateItinerarySpotRequest;
use App\Http\Resources\ItinerarySpotResource;
use App\Models\Itinerary;
use App\Models\ItinerarySpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ItinerarySpotController extends Controller
{
    public function index(Request $request, Itinerary $itinerary): AnonymousResourceCollection|JsonResponse
    {
        Gate::authorize('viewAny', [ItinerarySpot::class, $itinerary]);

        if ($request->group_by_date) {
            $grouped = $itinerary->spots()
                ->with('place.images')
                ->orderBy('visit_date')
                ->orderBy('sort_order')
                ->get()
                ->groupBy(fn (ItinerarySpot $spot) => $spot->visit_date->toDateString());

            return response()->json([
                'data' => $grouped->map(fn ($spots) => ItinerarySpotResource::collection($spots)),
            ]);
        }

        return ItinerarySpotResource::collection(
            $itinerary->spots()->with('place.images')->latest()->get()
        );
    }

    /**
     * Store a newly created spot in the given itinerary.
     */
    public function store(StoreItinerarySpotRequest $request, Itinerary $itinerary): JsonResponse
    {
        Gate::authorize('create', [ItinerarySpot::class, $itinerary]);

        $spot = $itinerary->spots()->make($request->validated());
        $spot->sort_order = $itinerary->nextSpotSortOrder($spot->visit_date->toDateString());
        $spot->save();

        return (new ItinerarySpotResource($spot->refresh()->load('place.images')))->response()->setStatusCode(201);
    }

    /**
     * Update the specified spot.
     */
    public function update(UpdateItinerarySpotRequest $request, Itinerary $itinerary, ItinerarySpot $spot): ItinerarySpotResource
    {
        Gate::authorize('update', [$spot, $itinerary]);

        $previous_visit_date = $spot->visit_date->toDateString();
        $spot->fill($request->validated());
        $is_moving_day = $spot->visit_date->toDateString() !== $previous_visit_date;

        if ($is_moving_day) {
            $spot->sort_order = $itinerary->nextSpotSortOrder($spot->visit_date->toDateString());
        }

        $spot->save();

        if ($is_moving_day) {
            $itinerary->renumberSpotsOn($previous_visit_date);
        }

        if ($spot->start_time !== null && ($is_moving_day || $spot->wasChanged('start_time'))) {
            $spot->moveIntoTimeOrder();
        }

        return new ItinerarySpotResource($spot->refresh()->load('place.images'));
    }

    /**
     * Reorder the spots of one day. The request has already authorized the user.
     */
    public function reorder(ReorderItinerarySpotsRequest $request, Itinerary $itinerary): JsonResponse
    {
        $itinerary->reorderSpots($request->validated('visit_date'), $request->validated('spot_ids'));

        return response()->json(null, 204);
    }

    /**
     * Remove the specified spot.
     */
    public function destroy(Request $request, Itinerary $itinerary, ItinerarySpot $spot): JsonResponse
    {
        Gate::authorize('delete', [$spot, $itinerary]);

        $visit_date = $spot->visit_date->toDateString();
        $spot->delete();
        $itinerary->renumberSpotsOn($visit_date);

        return response()->json(null, 204);
    }
}

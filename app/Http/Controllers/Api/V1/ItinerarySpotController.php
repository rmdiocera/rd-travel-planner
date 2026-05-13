<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
                ->with('place')
                ->orderBy('visit_date')
                ->orderBy('start_time')
                ->get()
                ->groupBy(fn (ItinerarySpot $spot) => $spot->visit_date->toDateString());

            return response()->json([
                'data' => $grouped->map(fn ($spots) => ItinerarySpotResource::collection($spots)),
            ]);
        }

        return ItinerarySpotResource::collection(
            $itinerary->spots()->with('place')->latest()->get()
        );
    }

    /**
     * Store a newly created spot in the given itinerary.
     */
    public function store(StoreItinerarySpotRequest $request, Itinerary $itinerary): JsonResponse
    {
        Gate::authorize('create', [ItinerarySpot::class, $itinerary]);

        $spot = $itinerary->spots()->create($request->validated());

        return (new ItinerarySpotResource($spot->load('place')))->response()->setStatusCode(201);
    }

    /**
     * Update the specified spot.
     */
    public function update(UpdateItinerarySpotRequest $request, Itinerary $itinerary, ItinerarySpot $spot): ItinerarySpotResource
    {
        Gate::authorize('update', [$spot, $itinerary]);

        $spot->update($request->validated());

        return new ItinerarySpotResource($spot->load('place'));
    }

    /**
     * Remove the specified spot.
     */
    public function destroy(Request $request, Itinerary $itinerary, ItinerarySpot $spot): JsonResponse
    {
        Gate::authorize('delete', [$spot, $itinerary]);

        $spot->delete();

        return response()->json(null, 204);
    }
}

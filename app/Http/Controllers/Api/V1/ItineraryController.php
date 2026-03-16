<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreItineraryRequest;
use App\Http\Requests\Api\V1\UpdateItineraryRequest;
use App\Http\Resources\ItineraryResource;
use App\Models\Itinerary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItineraryController extends Controller
{
    /**
     * Return a listing of the authenticated user's itineraries.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ItineraryResource::collection(
            $request->user()->itineraries()->latest()->get()
        );
    }

    /**
     * Store a newly created itinerary.
     */
    public function store(StoreItineraryRequest $request): JsonResponse
    {
        $itinerary = $request->user()->itineraries()->create($request->validated());

        return (new ItineraryResource($itinerary))->response()->setStatusCode(201);
    }

    /**
     * Return the specified itinerary.
     */
    public function show(Request $request, Itinerary $itinerary): ItineraryResource
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        return new ItineraryResource($itinerary);
    }

    /**
     * Update the specified itinerary.
     */
    public function update(UpdateItineraryRequest $request, Itinerary $itinerary): ItineraryResource
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $itinerary->update($request->validated());

        return new ItineraryResource($itinerary);
    }

    /**
     * Remove the specified itinerary.
     */
    public function destroy(Request $request, Itinerary $itinerary): JsonResponse
    {
        abort_if($itinerary->user_id !== $request->user()->id, 403);

        $itinerary->delete();

        return response()->json(null, 204);
    }
}

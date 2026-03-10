<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePlaceRequest;
use App\Http\Requests\Api\V1\UpdatePlaceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlaceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlaceResource::collection(Place::query()->with('tags')->latest()->get());
    }

    public function store(StorePlaceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $place = Place::create($request->safe()->except(['tags']));

        if (! empty($validated['tags'])) {
            $place->tags()->attach($validated['tags']);
        }

        return (new PlaceResource($place->load('tags')))->response()->setStatusCode(201);
    }

    public function show(Place $place): PlaceResource
    {
        return new PlaceResource($place->load('tags'));
    }

    public function update(UpdatePlaceRequest $request, Place $place): PlaceResource
    {
        $validated = $request->validated();

        $place->update($request->safe()->except(['tags']));

        if (! empty($validated['tags'])) {
            $place->tags()->sync($validated['tags']);
        }

        return new PlaceResource($place->load('tags'));
    }

    public function destroy(Place $place): JsonResponse
    {
        $place->delete();

        return response()->json(null, 204);
    }
}

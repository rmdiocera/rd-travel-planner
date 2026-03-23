<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\ItinerarySpotController;
use Illuminate\Support\Facades\Route;

Route::apiResource('itineraries', ItineraryController::class);

Route::apiResource('itineraries/{itinerary}/spots', ItinerarySpotController::class)
    ->parameters(['spots' => 'spot'])
    ->except(['show']);

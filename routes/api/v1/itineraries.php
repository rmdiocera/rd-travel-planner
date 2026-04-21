<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\ItineraryListController;
use App\Http\Controllers\Api\V1\ItineraryListItemChecklistItemController;
use App\Http\Controllers\Api\V1\ItineraryListItemController;
use App\Http\Controllers\Api\V1\ItinerarySpotController;
use Illuminate\Support\Facades\Route;

Route::apiResource('itineraries', ItineraryController::class);

Route::apiResource('itineraries/{itinerary}/spots', ItinerarySpotController::class)
    ->parameters(['spots' => 'spot'])
    ->except(['show']);

Route::patch('itineraries/{itinerary}/lists/reorder', [ItineraryListController::class, 'reorder'])->name('itineraries.lists.reorder');

Route::apiResource('itineraries/{itinerary}/lists', ItineraryListController::class)
    ->parameters(['lists' => 'list'])
    ->except(['show']);

Route::patch('itineraries/{itinerary}/lists/{list}/items/reorder', [ItineraryListItemController::class, 'reorderListItems'])->name('itineraries.lists.items.reorder');

Route::apiResource('itineraries/{itinerary}/lists/{list}/items', ItineraryListItemController::class)
    ->parameters(['items' => 'item'])
    ->except(['index', 'show']);

Route::patch('itineraries/{itinerary}/lists/{list}/items/{item}/checklist/reorder', [ItineraryListItemChecklistItemController::class, 'reorder'])->name('itineraries.lists.items.checklist.reorder');

Route::apiResource('itineraries/{itinerary}/lists/{list}/items/{item}/checklist', ItineraryListItemChecklistItemController::class)
    ->parameters(['checklist' => 'checklistItem'])
    ->only(['store', 'update', 'destroy']);

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ItineraryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('itineraries', ItineraryController::class);

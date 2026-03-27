<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PlaceController;
use Illuminate\Support\Facades\Route;

Route::apiResource('places', PlaceController::class);

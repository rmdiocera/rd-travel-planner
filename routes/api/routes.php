<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    require __DIR__.'/v1/auth.php';

    Route::middleware('auth:sanctum')->group(function (): void {
        require __DIR__.'/v1/places.php';
        require __DIR__.'/v1/itineraries.php';
    });
});

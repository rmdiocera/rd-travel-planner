<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    require __DIR__.'/v1/auth.php';

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('user', function (Request $request) {
            return $request->user();
        });

        Route::middleware('verified')->group(function (): void {
            require __DIR__.'/v1/places.php';
            require __DIR__.'/v1/itineraries.php';
            require __DIR__.'/v1/tags.php';
        });
    });
});

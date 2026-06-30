<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Support\Facades\Route;

Route::get('tags', [TagController::class, 'index']);

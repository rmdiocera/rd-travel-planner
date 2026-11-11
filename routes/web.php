<?php

use App\Http\Controllers\Auth\VerifyEmailAndLoginController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('email/verify-and-login/{id}/{hash}', VerifyEmailAndLoginController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.login');

require __DIR__.'/settings.php';

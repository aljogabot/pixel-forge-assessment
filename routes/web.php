<?php

use App\Http\Controllers\Generations\GenerationController;
use App\Http\Controllers\Webhooks\PixelForgeWebhookController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__ . '/settings.php';


Route::post('generations/estimate', [
    GenerationController::class,
    'estimate',
])
    ->middleware('throttle:30,1')
    ->name('generations.estimate');




Route::post('webhooks/pixelforge', PixelForgeWebhookController::class)
    ->name('webhooks.pixelforge');

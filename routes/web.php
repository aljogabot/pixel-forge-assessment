<?php

use App\Http\Controllers\Generations\GenerationController;
use App\Http\Controllers\Webhooks\ImageProviderWebhookController;
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

Route::post('generations', [
    GenerationController::class,
    'store',
])
    ->middleware('throttle:10,1')
    ->name('generations.store');


Route::post('webhooks/pixelforge', ImageProviderWebhookController::class)
    ->name('webhooks.pixelforge');

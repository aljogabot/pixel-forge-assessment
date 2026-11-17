<?php

use App\Http\Controllers\Generations\GenerationController;
use App\Http\Controllers\Webhooks\ImageProviderWebhookController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

require __DIR__ . '/settings.php';

Route::post('webhooks/pixelforge', ImageProviderWebhookController::class)
    ->name('webhooks.pixelforge');

// Dashboard Authenticated Routes ...
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [
        GenerationController::class,
        'index',
    ])->name('dashboard');

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

    Route::get('generations/create', [GenerationController::class, 'create',])->name('generations.create');
});

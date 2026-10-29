<?php

use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// The app's API, under /api/v1. It knows products, never people: the health profile and the scoring stay on the
// phone. Every answer is JSON, there is no cookie, and each address is rate-limited (AppServiceProvider).
Route::prefix('v1')->name('api.')->group(function () {
    Route::middleware('throttle:lookups')->group(function () {
        Route::get('products/{barcode}', [ProductController::class, 'show'])->name('product');
        Route::get('products', [ProductController::class, 'index'])->name('products');
        Route::get('updates', fn () => ['updates' => config('hooli.updates')])->name('updates');
    });

    Route::post('labels', LabelController::class)->middleware('throttle:labels')->name('labels');
});

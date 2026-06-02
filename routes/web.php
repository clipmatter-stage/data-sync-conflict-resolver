<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Install page - no authentication required
Route::get('/install', fn() => Inertia::render('install'))->name('install');

// Root route - redirect to install if no shop parameter
Route::get('/', function () {
    if (!request()->has('shop') && !auth('shopify')->check()) {
        return redirect()->route('install');
    }
    return app(\App\Http\Controllers\ProductSync\ProductSyncDashboardController::class)->index();
})->middleware('verify.shopify')->name('home');

// Shopify App Routes
Route::middleware(['verify.shopify'])->group(function () {

    // Original Shopify Dashboard (accessible via /welcome if needed)
    Route::get('/welcome', fn() => Inertia::render('shopify-dashboard', [
        'shop' => request()->input('shop'),
    ]))->name('welcome');
});

// Standard Laravel Routes (for non-Shopify access)
// Route::get('/welcome', function () {
//     return Inertia::render('welcome');
// })->name('welcome');

// Route::middleware(['auth'])->group(function () {
//     Route::get('dashboard', function () {
//         return Inertia::render('dashboard');
//     })->name('dashboard');
// });

// Akeneo PIM API Routes
Route::prefix('akeneo')->name('akeneo.')->group(function () {
    Route::get('/test', [\App\Http\Controllers\AkeneoController::class, 'testConnection'])->name('test');
    Route::get('/products', [\App\Http\Controllers\AkeneoController::class, 'fetchProducts'])->name('products');
    Route::get('/products/{identifier}', [\App\Http\Controllers\AkeneoController::class, 'fetchProduct'])->name('product');
});

// Product Sync Conflict Resolver Routes
Route::middleware(['verify.shopify'])->prefix('product-sync')->name('product-sync.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\ProductSync\ProductSyncDashboardController::class, 'index'])->name('dashboard');

    // Sync
    Route::post('/sync', [\App\Http\Controllers\ProductSync\ProductSyncController::class, 'sync'])->name('sync');

    // Conflicts
    Route::get('/conflicts', [\App\Http\Controllers\ProductSync\ProductConflictController::class, 'index'])->name('conflicts.index');
    Route::get('/conflicts/{conflict}', [\App\Http\Controllers\ProductSync\ProductConflictController::class, 'show'])->name('conflicts.show');
    Route::post('/conflicts/{conflict}/resolve', [\App\Http\Controllers\ProductSync\ProductConflictController::class, 'resolve'])->name('conflicts.resolve');
    Route::post('/conflicts/{conflict}/ignore', [\App\Http\Controllers\ProductSync\ProductConflictController::class, 'ignore'])->name('conflicts.ignore');

    // Logs
    Route::get('/logs', [\App\Http\Controllers\ProductSync\ProductSyncLogController::class, 'index'])->name('logs.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LinkController;
use PhpParser\Node\Stmt\Foreach_;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\AkeneoController;

// Akeneo PIM API Routes
Route::prefix('akeneo')->group(function () {
    Route::get('/test-connection', [AkeneoController::class, 'testConnection']);
    Route::get('/products', [AkeneoController::class, 'fetchProducts']);
    Route::get('/products/{identifier}', [AkeneoController::class, 'fetchProduct']);
});
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/exercise-1-artwork-version', function (Request $request) {
    $validated = $request->validate([
        'input' => 'required|array',
        'input.*.id' => 'required|integer',
        'input.*.time' => 'required|integer',
        'input.*.approved' => 'required|boolean',
        'input.*.rejected' => 'required|boolean',
    ]);
    $input = $validated['input'];
    $validItem = null;
    foreach ($input as $item) {
        if ($item['approved'] === true && $item['rejected'] === false) {
            if ($validItem === null) {
                $validItem = $item;
            } else if ($item['time'] >= $validItem['time'] && $item['id'] == max(array_column($input, 'id'))) {
                $validItem = $item;
            }
        }
    }
    if ($validItem) {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $validItem['id'],
            ],
            "error" => null
        ]);
    } else {
        return response()->json([
            'success' => false,
            'data' => null,
            "error" => "No valid item found"
        ]);
    }
});
Route::post('/exercise-2-tier-pricing', [ExerciseController::class, 'tierPricing']);
Route::post('/exercise-3-cart-validator', [ExerciseController::class, 'cartValidator']);
Route::post('/exercise-4-vendor-allocation', [ExerciseController::class, 'vendorAllocation']);
Route::post('/exercise-5-discount', [ExerciseController::class, 'discount']);
Route::post('/exercise-6-approval-flow', [ExerciseController::class, 'approvalFlow']);
Route::post('/exercise-7-inventory', [ExerciseController::class, 'inventory']);
Route::post('/exercise-8-shipment', [ExerciseController::class, 'shipment']);
Route::post('/exercise-9-webhook', [ExerciseController::class, 'webhook']);
Route::post('/exercise-10-quote-expiry', [ExerciseController::class, 'quoteExpiry']);
Route::post('/exercise-11-product-visibility', [ExerciseController::class, 'productVisibility']);
Route::post('/exercise-12-bundle-pricing', [ExerciseController::class, 'bundlePricing']);
Route::post('/exercise-13-cart-merge', [ExerciseController::class, 'cartMerge']);
Route::post('/exercise-14-upsell', [ExerciseController::class, 'upsell']);
Route::post('/exercise-15-shipping-rule', [ExerciseController::class, 'shippingRuleEngine']);
Route::post('/exercise-16-fraud-check', [ExerciseController::class, 'fraudCheck']);
Route::post('/exercise-17-shopify-price-adjustment', [ExerciseController::class, 'shopifyPriceAdjustment']);
Route::post('/exercise-18-data-sync', [ExerciseController::class, 'dataSync']);
Route::post('/exercise-19-variant-control', [ExerciseController::class, 'variantControl']);
Route::post('/exercise-20-order-state', [ExerciseController::class, 'orderState']);
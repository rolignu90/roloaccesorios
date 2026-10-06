<?php

use App\Http\Controllers\Api\Ecommerce\ComboController;
use App\Http\Controllers\Api\Ecommerce\OrderController;
use App\Http\Controllers\Api\Ecommerce\ProductController;
use App\Http\Controllers\Api\Ecommerce\ShippingCarrierController;
use Illuminate\Support\Facades\Route;

Route::prefix('ecommerce/v1')
    ->middleware(['ecommerce.api', 'throttle:60,1'])
    ->group(function () {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{code}', [ProductController::class, 'show']);
        Route::get('combos', [ComboController::class, 'index']);
        Route::get('shipping-carriers', [ShippingCarrierController::class, 'index']);

        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/by-number/{number}', [OrderController::class, 'showByNumber']);
        Route::get('orders/{externalOrderId}', [OrderController::class, 'show']);
    });

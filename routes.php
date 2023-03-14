<?php

use Illuminate\Support\Facades\Route;
use Omconnect\Pay\Http\Controllers\AndroidPayController;
use Omconnect\Pay\Http\Controllers\IosPayController;

Route::group([
    'prefix' => 'ompay',
], function () {
    Route::group([
        'prefix' => 'iospay',
    ], function () {
        Route::post('notification', [IosPayController::class, 'notification']);
    });

    Route::group([
        'prefix' => 'androidpay',
    ], function () {
        Route::post('notification', [AndroidPayController::class, 'notification']);
    });
    
    Route::group([
        'prefix' => 'stripe',
    ], function () {
        Route::post('', [StripeController::class, 'handle']);
    });
});



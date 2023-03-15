# OMPAY

```
<?php

use Illuminate\Support\Facades\Route;
use Omconnect\Pay\Http\Controllers\AndroidPayController;
use Omconnect\Pay\Http\Controllers\IosPayController;
use Omconnect\Pay\Http\Controllers\StripeController;
use Omconnect\Pay\Http\Controllers\SubscriptionController;

Route::group([
    'prefix' => 'ompay',
], function () {
    Route::group([
        'prefix' => 'subscription',
        'middleware' => ['auth:api'],
    ], function () {
        Route::get('list', [SubscriptionController::class, 'list']);
        Route::post('verify', [SubscriptionController::class, 'verify']);
        Route::get('list-all', [SubscriptionController::class, 'listAll']);
    });
    
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
```
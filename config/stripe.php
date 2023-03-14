<?php

return [
    'api_key' => env('STRIPE_API_KEY'),
    'webhook_key' => env('STRIPE_WH_KEY'),
    'payment_methods' => env('STRIPE_PAYMENT_METHODS', 'card'),
    'success_url' => env('STRIPE_URL_SUCCESS'),
    'cancel_url' => env('STRIPE_URL_CANCEL'),
    'subscription_success_url' => env('STRIPE_SUBSCRIPTION_URL_SUCCESS'),
    'subscription_cancel_url' => env('STRIPE_SUBSCRIPTION_URL_CANCEL'),
];

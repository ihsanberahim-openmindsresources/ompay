<?php

namespace Omconnect\Pay;

use Illuminate\Support\ServiceProvider;
use Omconnect\Pay\Services\AndroidPayService;
use Omconnect\Pay\Services\IosPayService;
use Omconnect\Pay\Services\StripeService;

class OmPayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        /*
         * Optional methods to load your package assets
         */
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'ompay');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'ompay');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('ompay.php'),
            ], 'config');

            // Publishing the views.
            /*$this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/ompay'),
            ], 'views');*/

            // Publishing assets.
            /*$this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/ompay'),
            ], 'assets');*/

            // Publishing the translation files.
            /*$this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/ompay'),
            ], 'lang');*/

            // Registering package commands.
            // $this->commands([]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'ompay');
        $this->mergeConfigFrom(__DIR__.'/../config/stripe.php', 'stripe');

        // Register the main class to use with the facade
        $this->app->singleton('ompay', function () {
            return new OmPay;
        });

        $this->app->singleton(StripeService::class, function ($app) {
            return new StripeService(
                config('stripe.api_key'),
                config('stripe.payment_methods'),
                config('stripe.success_url'),
                config('stripe.cancel_url'),
                config('stripe.subscription_success_url'),
                config('stripe.subscription_cancel_url'),
            );
        });

        $this->app->singleton(IosPayService::class, function ($app) {
            return new IosPayService(
                config('ompay.apple_iapsecret'),
            );
        });

        $this->app->singleton(AndroidPayService::class, function ($app) {
            return new AndroidPayService(
                config('ompay.google_keyfile'),
                config('ompay.android_package_name'),
            );
        });
    }
}

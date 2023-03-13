<?php

namespace Omconnect\Pay;

use Illuminate\Support\ServiceProvider;
use Omconnect\Pay\Services\AndroidPayService;
use Omconnect\Pay\Services\IosPayService;

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

        // Register the main class to use with the facade
        $this->app->singleton('ompay', function () {
            return new OmPay;
        });

        $this->app->singleton(IosPayService::class, function ($app) {
            return new IosPayService(
                config('ompay.apple_appid'),
                config('ompay.apple_teamid'),
                config('ompay.apple_keyid'),
                config('ompay.apple_keyfile'),
                config('ompay.apple_iapsecret'),
            );
        });

        $this->app->singleton(AndroidPayService::class, function ($app) {
            return new AndroidPayService(
                config('ompay.google_appid'),
                config('ompay.google_keyfile'),
                config('ompay.google_iapbundle'),
            );
        });
    }
}

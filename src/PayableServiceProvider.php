<?php

namespace Marshmallow\Payable;

use Illuminate\Support\ServiceProvider;

class PayableServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->mergeConfigFrom(
            __DIR__.'/../config/payable.php',
            'payable'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        /**
         * Routes
         */
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        $this->publishes([
            __DIR__.'/../config/payable.php' => config_path('payable.php'),
        ]);
    }
}

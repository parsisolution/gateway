<?php

namespace Parsisolution\Gateway;

use Illuminate\Support\ServiceProvider;
use Parsisolution\Gateway\Contracts\Factory;

class GatewayServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $config = __DIR__.'/../config/'.GatewayManager::CONFIG_FILE_NAME.'.php';
        $migrations = __DIR__.'/../migrations/';
        $views = __DIR__.'/../views/';

        //php artisan vendor:publish --provider="Parsisolution\Gateway\GatewayServiceProvider" --tag=config
        $this->publishes([
            $config => config_path(GatewayManager::CONFIG_FILE_NAME.'.php'),
        ], 'config');

        //$this->mergeConfigFrom( $config, Gate)

        // php artisan vendor:publish --provider="Parsisolution\Gateway\GatewayServiceProvider" --tag=migrations
        $this->publishes([
            $migrations => base_path('database/migrations'),
        ], 'migrations');

        $this->loadViewsFrom($views, 'gateway');

        // php artisan vendor:publish --provider="Parsisolution\Gateway\GatewayServiceProvider" --tag=views
        $this->publishes([
            $views => base_path('resources/views/vendor/gateway'),
        ], 'views');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Factory::class, function ($app) {
            return new GatewayManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [Factory::class];
    }
}

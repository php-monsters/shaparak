<?php

namespace PhpMonsters\Shaparak;

use Illuminate\Support\ServiceProvider;
use PhpMonsters\Shaparak\Contracts\Factory;

class ShaparakServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerResources();
        $this->registerPublishing();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Factory::class, function ($app) {
            return new ShaparakManager($app);
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

    /**
     * Determine if the provider is deferred.
     *
     * @return bool
     */
    public function isDeferred()
    {
        return true;
    }

    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../views/', 'shaparak');

        $this->publishes([
            __DIR__.'/../translations/' => base_path('/lang/vendor/shaparak'),
        ], 'translations');

        $this->loadTranslationsFrom(__DIR__.'/../translations', 'shaparak');
    }

    protected function registerPublishing()
    {
        $this->publishes([
            __DIR__.'/../views/' => resource_path('/views/vendor/shaparak'),
        ], 'views');

        $this->publishes([
            __DIR__.'/../config/shaparak.php' => config_path('shaparak.php'),
        ], 'config');
    }
}

<?php

namespace Bulbula\Shop\Providers;

use Illuminate\Support\ServiceProvider;
use Bulbula\Shop\Models\Order;
use Bulbula\Shop\Observers\OrderObserver;

class ShopServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();
        $this->registerRepositories();
        $this->registerServices();
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'shop');
        
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'shop');
        
        $this->publishes([
            __DIR__ . '/../Resources/assets' => public_path('vendor/shop'),
        ], 'shop-assets');

        Order::observe(OrderObserver::class);
        
        // Register middleware for frontend data sharing
        $this->registerMiddleware();
    }
    
    /**
     * Register middleware.
     *
     * @return void
     */
    protected function registerMiddleware()
    {
        $router = $this->app['router'];
        
        // Add setup incomplete check to web middleware group
        $router->pushMiddlewareToGroup('web', 'setup.incomplete');
        
        // Add to web middleware group
        $router->pushMiddlewareToGroup('web', \Bulbula\Shop\Http\Middleware\ShareFrontendData::class);
    }

    /**
     * Register package config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/shop.php',
            'shop'
        );
    }

    /**
     * Register repositories.
     *
     * @return void
     */
    protected function registerRepositories()
    {
        $this->app->singleton(
            \Bulbula\Shop\Contracts\ProductRepositoryInterface::class,
            \Bulbula\Shop\Repositories\ProductRepository::class
        );

        $this->app->singleton(
            \Bulbula\Shop\Contracts\CategoryRepositoryInterface::class,
            \Bulbula\Shop\Repositories\CategoryRepository::class
        );

        $this->app->singleton(
            \Bulbula\Shop\Contracts\OrderRepositoryInterface::class,
            \Bulbula\Shop\Repositories\OrderRepository::class
        );
    }

    /**
     * Register services.
     *
     * @return void
     */
    protected function registerServices()
    {
        $this->app->singleton(
            \Bulbula\Shop\Services\HomeService::class,
            function ($app) {
                return new \Bulbula\Shop\Services\HomeService(
                    $app->make(\Bulbula\Shop\Contracts\ProductRepositoryInterface::class),
                    $app->make(\Bulbula\Shop\Contracts\CategoryRepositoryInterface::class)
                );
            }
        );

        $this->app->singleton(
            \Bulbula\Shop\Services\ProductService::class,
            function ($app) {
                return new \Bulbula\Shop\Services\ProductService(
                    $app->make(\Bulbula\Shop\Contracts\ProductRepositoryInterface::class)
                );
            }
        );

        $this->app->singleton(
            \Bulbula\Shop\Services\CategoryService::class,
            function ($app) {
                return new \Bulbula\Shop\Services\CategoryService(
                    $app->make(\Bulbula\Shop\Contracts\CategoryRepositoryInterface::class)
                );
            }
        );

        $this->app->singleton(
            \Bulbula\Shop\Services\CheckoutService::class,
            function ($app) {
                return new \Bulbula\Shop\Services\CheckoutService(
                    $app->make(\Bulbula\Shop\Contracts\OrderRepositoryInterface::class),
                    $app->make(\Bulbula\Admin\Services\AdminNotificationService::class),
                    $app->make(\Bulbula\Marketing\Services\CouponService::class),
                    $app->make(\Bulbula\Sales\Services\ManualPaymentService::class)
                );
            }
        );
    }
}

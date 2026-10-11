<?php

declare(strict_types=1);

namespace Bulbula\CMS\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Bulbula\CMS\Repositories\BlockRepository;
use Bulbula\CMS\Services\BlockService;

class CMSServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cms.php',
            'cms'
        );

        // Register services
            $this->app->singleton(\Bulbula\CMS\Services\PageService::class);
            $this->app->singleton(\Bulbula\CMS\Repositories\PageRepository::class);
            $this->app->singleton(\Bulbula\CMS\Services\BlockService::class);
            $this->app->singleton(\Bulbula\CMS\Repositories\BlockRepository::class);
            $this->app->singleton(\Bulbula\CMS\Services\StorefrontMenuSyncService::class);
            $this->app->singleton(\Bulbula\CMS\Services\StorefrontMenuService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../Routes/admin.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/cms.php' => config_path('cms.php'),
        ], 'cms-config');

        // Register @block Blade directive
        $this->registerBlockDirective();
    }

    /**
     * Register @block Blade directive for rendering blocks.
     */
    protected function registerBlockDirective(): void
    {
        Blade::directive('block', function ($expression) {
            return "<?php echo app(\Bulbula\CMS\Services\BlockService::class)->renderBlock(app(\Bulbula\CMS\Repositories\BlockRepository::class)->findByIdentifier({$expression})); ?>";
        });
    }
}

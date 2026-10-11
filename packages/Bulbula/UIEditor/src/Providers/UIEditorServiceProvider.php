<?php

declare(strict_types=1);

namespace Bulbula\UIEditor\Providers;

use Illuminate\Support\ServiceProvider;
use Bulbula\UIEditor\Services\BlockRegistry;
use Bulbula\UIEditor\Services\LayoutService;
use Bulbula\UIEditor\Repositories\LayoutRepository;

class UIEditorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LayoutRepository::class);
        $this->app->singleton(LayoutService::class);
        $this->app->singleton(BlockRegistry::class, function ($app) {
            $registry = new BlockRegistry();
            $registry->registerDefaults();
            return $registry;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Load admin routes
        $this->loadRoutesFrom(__DIR__ . '/../Routes/admin.php');

        // Fire hook so extensions can register custom block types
        $this->app->booted(function () {
            if ($this->app->bound(\Bulbula\Core\Services\HookService::class)) {
                $registry = $this->app->make(BlockRegistry::class);
                app(\Bulbula\Core\Services\HookService::class)
                    ->doAction('uieditor.register_blocks', $registry);
            }
        });

        // UIEditor sidebar items are intentionally not registered here.
        // The Block Editor is launched directly from the Pages list (like Elementor).
        // Add a 'Blocks' menu item for the saved reusable blocks library when that feature is ready.
    }
}

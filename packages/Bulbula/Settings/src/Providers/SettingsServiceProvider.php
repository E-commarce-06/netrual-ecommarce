<?php

namespace Bulbula\Settings\Providers;

use Bulbula\Settings\Mcp\McpToolRegistry;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(McpToolRegistry::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/mcp.php');
    }
}

<?php

namespace Bulbula\Core\Providers;

use Bulbula\Core\Console\Commands\ExtensionMakeCommand;
use Bulbula\Core\Console\Commands\ExtensionsActivateCommand;
use Bulbula\Core\Console\Commands\ExtensionsDeactivateCommand;
use Bulbula\Core\Console\Commands\ExtensionsInstallCommand;
use Bulbula\Core\Console\Commands\ExtensionsListCommand;
use Bulbula\Core\Console\Commands\ExtensionsSyncCommand;
use Bulbula\Core\Console\Commands\ExtensionsUninstallCommand;
use Bulbula\Core\Console\Commands\InstallCommand;
use Bulbula\Core\Console\Commands\ThemeDiscoverCommand;
use Bulbula\Core\Http\Middleware\SetAdminSessionCookie;
use Bulbula\Core\Services\ExtensionService;
use Bulbula\Core\Services\HookService;
use Bulbula\Core\Services\MenuService;
use Bulbula\Core\Services\PaymentGatewayManager;
use Bulbula\Core\Services\SettingService;
use Bulbula\Core\Services\ThemePathResolver;
use Bulbula\Core\Services\ThemeService;
use Bulbula\Core\Services\ThemeViewResolver;
use Bulbula\UIEditor\Services\LayoutService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register HookService as singleton
        $this->app->singleton('bulbula.hook', function ($app) {
            return new HookService;
        });

        // Register MenuService as singleton
        $this->app->singleton('bulbula.menu', function ($app) {
            return new MenuService;
        });

        // Register SettingService as singleton
        $this->app->singleton('bulbula.setting', function ($app) {
            return new SettingService;
        });

        // Register ExtensionService as singleton
        $this->app->singleton('bulbula.extension', function ($app) {
            return new ExtensionService(
                $app->make('bulbula.hook'),
                $app->make('bulbula.menu')
            );
        });

        $this->app->singleton(ThemePathResolver::class, function () {
            return new ThemePathResolver;
        });

        // Register ThemeService as singleton
        $this->app->singleton('bulbula.theme', function ($app) {
            return new ThemeService($app->make(ThemePathResolver::class));
        });





        // Bind ThemeService class to service container
        $this->app->bind(ThemeService::class, function ($app) {
            return $app->make('bulbula.theme');
        });

        // Register ThemeViewResolver as singleton
        $this->app->singleton('bulbula.theme.resolver', function ($app) {
            return new ThemeViewResolver($app->make(ThemePathResolver::class));
        });

        // Bind ThemeViewResolver class to service container
        $this->app->bind(ThemeViewResolver::class, function ($app) {
            return $app->make('bulbula.theme.resolver');
        });

        // Register PaymentGatewayManager as singleton
        $this->app->singleton('bulbula.payment.gateway', function ($app) {
            return new PaymentGatewayManager;
        });

        // Bind PaymentGatewayManager class to service container
        $this->app->bind(PaymentGatewayManager::class, function ($app) {
            return $app->make('bulbula.payment.gateway');
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ExtensionsListCommand::class,
                ExtensionsSyncCommand::class,
                ExtensionMakeCommand::class,
                ExtensionsInstallCommand::class,
                ExtensionsUninstallCommand::class,
                ExtensionsActivateCommand::class,
                ExtensionsDeactivateCommand::class,
                ThemeDiscoverCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Give the admin panel its own session cookie so admin and storefront
        // users can be logged in simultaneously in the same browser.
        $this->app->make(Kernel::class)
            ->prependMiddleware(SetAdminSessionCookie::class);

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../Routes/admin.php');

        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'core');

        // Publish configuration
        $this->publishes([
            __DIR__.'/../Config/core.php' => config_path('core.php'),
        ], 'core-config');

        // Publish translations
        $this->publishes([
            __DIR__.'/../Resources/lang' => lang_path('vendor/core'),
        ], 'core-translations');

        // Boot themes — discover filesystem, load active theme assets
        $this->bootThemes();

        // Boot active extensions
        $this->bootExtensions();
    }

    /**
     * Discover themes from filesystem and boot the active theme's assets/hooks.
     */
    protected function bootThemes(): void
    {
        try {
            $this->removeStaleViteHotFile();

            /** @var ThemeService $themeService */
            $themeService = $this->app->make('bulbula.theme');

            // Auto-discover storefront templates from templates/storefront/ into the DB
            $themeService->discover();

            // Load asset paths and hooks for the currently active theme
            $activeTheme = $themeService->reconcileActiveTheme() ?? $themeService->active();

            if ($activeTheme) {
                config(['theme.active' => $activeTheme->slug]);
                $themeService->loadAssets($activeTheme);
            }
        } catch (\Exception $e) {
            // Silently fail during install/migration when tables don't exist yet
        }
    }

    /**
     * Remove public/hot when the Vite dev server is no longer reachable.
     * A stale hot file makes @vite point at :5173 and breaks all JS/CSS the next day.
     */
    protected function removeStaleViteHotFile(): void
    {
        $hotPath = public_path('hot');

        if (! is_file($hotPath)) {
            return;
        }

        $devServerUrl = trim((string) file_get_contents($hotPath));

        if ($devServerUrl === '') {
            @unlink($hotPath);

            return;
        }

        $parts = parse_url($devServerUrl);
        $host = $parts['host'] ?? '127.0.0.1';
        $scheme = $parts['scheme'] ?? 'http';
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = $parts['path'] ?? '/';
        $probeUrl = "{$scheme}://{$host}:{$port}{$path}";

        $reachable = false;

        if (function_exists('curl_init')) {
            $handle = curl_init($probeUrl);
            curl_setopt_array($handle, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            curl_exec($handle);
            $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            $reachable = $httpCode >= 200 && $httpCode < 500;
        }

        if ($reachable) {
            return;
        }

        @unlink($hotPath);
    }

    /**
     * Boot active extensions.
     */
    protected function bootExtensions(): void
    {
        try {
            $extensionService = $this->app->make('bulbula.extension');
            $activeExtensions = $extensionService->getActive();

            foreach ($activeExtensions as $extension) {
                // Load extension service provider if exists
                $discovered = $extensionService->discover()->firstWhere('manifest.code', $extension->code);

                if ($discovered && isset($discovered['manifest']['provider'])) {
                    $providerClass = $discovered['manifest']['provider'];

                    // Prefer autoloaded providers (bundled / composer packages)
                    if (class_exists($providerClass)) {
                        $this->app->register($providerClass);

                        continue;
                    }

                    // Support explicit provider file path
                    $providerFile = $discovered['manifest']['provider_file'] ?? null;
                    if ($providerFile) {
                        $providerPath = rtrim($discovered['path'], '/\\').'/'.ltrim($providerFile, '/\\');
                        if (file_exists($providerPath)) {
                            require_once $providerPath;
                            if (class_exists($providerClass)) {
                                $this->app->register($providerClass);
                            }
                        }

                        continue;
                    }

                    // Legacy fallback
                    $providerPath = $discovered['path'].'/src/'.str_replace('\\', '/', $providerClass).'.php';
                    if (file_exists($providerPath)) {
                        require_once $providerPath;
                        if (class_exists($providerClass)) {
                            $this->app->register($providerClass);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail during installation when tables don't exist yet
        }
    }
}

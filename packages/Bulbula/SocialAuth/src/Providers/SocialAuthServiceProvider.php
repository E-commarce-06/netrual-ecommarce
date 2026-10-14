<?php

namespace Bulbula\SocialAuth\Providers;

use Bulbula\SocialAuth\Services\GoogleAuthSettings;
use Bulbula\SocialAuth\Services\GoogleIdentityTokenVerifier;
use Bulbula\SocialAuth\Services\GoogleOAuthProviderFactory;
use Bulbula\SocialAuth\Services\SocialAuthAccountService;
use Bulbula\SocialAuth\Services\SocialAuthChallengeService;
use Illuminate\Support\ServiceProvider;

class SocialAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleAuthSettings::class);
        $this->app->singleton(GoogleIdentityTokenVerifier::class);
        $this->app->singleton(GoogleOAuthProviderFactory::class);
        $this->app->singleton(SocialAuthAccountService::class);
        $this->app->singleton(SocialAuthChallengeService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/admin.php');
    }
}

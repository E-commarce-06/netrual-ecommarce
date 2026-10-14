<?php

namespace Bulbula\SocialAuth\Services;

use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use LogicException;

class GoogleOAuthProviderFactory
{
    public function make(string $environment): GoogleProvider
    {
        $settings = app(GoogleAuthSettings::class);
        $profile = $settings->profile($environment);

        if ($profile['client_id'] === '' || $profile['client_secret'] === '') {
            throw new LogicException('The selected Google OAuth profile is incomplete.');
        }

        $factory = app(Factory::class);

        if (! $factory instanceof SocialiteManager) {
            throw new LogicException('Laravel Socialite is not available.');
        }

        /** @var GoogleProvider $provider */
        $provider = $factory->buildProvider(GoogleProvider::class, [
            'client_id' => $profile['client_id'],
            'client_secret' => $profile['client_secret'],
            'redirect' => $settings->callbackUrl(),
            'scopes' => ['openid', 'email', 'profile'],
            'guzzle' => [
                'timeout' => 10,
                'connect_timeout' => 4,
                'allow_redirects' => false,
            ],
        ]);

        return $provider->with(['prompt' => 'select_account']);
    }
}

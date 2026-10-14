<?php

namespace Bulbula\SocialAuth\Services;

use Bulbula\SocialAuth\Models\SocialAuthConfiguration;
use Throwable;

class GoogleAuthSettings
{
    public function current(): SocialAuthConfiguration
    {
        return SocialAuthConfiguration::query()->firstOrCreate(
            ['provider' => 'google'],
            [
                'enabled' => false,
                'active_environment' => 'test',
                'web_enabled' => false,
                'api_enabled' => false,
                'registration_enabled' => true,
                'max_id_token_age_minutes' => 10,
                'test_api_audiences' => [],
                'live_api_audiences' => [],
            ]
        );
    }

    /**
     * Return credentials for server-side use only. Never pass this result to a view.
     *
     * @return array{environment:string,client_id:string,client_secret:string,api_audiences:array<int,string>}
     */
    public function profile(?string $environment = null): array
    {
        $configuration = $this->current();
        $environment ??= $configuration->active_environment;

        if (! in_array($environment, ['test', 'live'], true)) {
            $environment = 'test';
        }

        $clientId = trim((string) $configuration->getAttribute("{$environment}_client_id"));

        try {
            $clientSecret = (string) ($configuration->getAttribute("{$environment}_client_secret") ?? '');
        } catch (Throwable) {
            // A changed APP_KEY or corrupt ciphertext must disable OAuth, not leak or crash the page.
            $clientSecret = '';
        }

        $audiences = $configuration->getAttribute("{$environment}_api_audiences") ?? [];

        return [
            'environment' => $environment,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'api_audiences' => array_values(array_unique(array_filter(
                array_map(static fn ($audience) => trim((string) $audience), is_array($audiences) ? $audiences : []),
                static fn (string $audience) => $audience !== ''
            ))),
        ];
    }

    public function activeEnvironment(): string
    {
        $environment = (string) $this->current()->active_environment;

        return in_array($environment, ['test', 'live'], true) ? $environment : 'test';
    }

    public function webLoginEnabled(?string $environment = null): bool
    {
        $configuration = $this->current();
        $profile = $this->profile($environment);

        return $configuration->enabled
            && $configuration->web_enabled
            && $profile['client_id'] !== ''
            && $profile['client_secret'] !== '';
    }

    public function apiLoginEnabled(?string $environment = null): bool
    {
        $configuration = $this->current();
        $profile = $this->profile($environment);

        return $configuration->enabled
            && $configuration->api_enabled
            && $profile['api_audiences'] !== [];
    }

    public function webRegistrationEnabled(?string $environment = null): bool
    {
        return $this->current()->registration_enabled && $this->webLoginEnabled($environment);
    }

    public function apiRegistrationEnabled(?string $environment = null): bool
    {
        return $this->current()->registration_enabled && $this->apiLoginEnabled($environment);
    }

    public function callbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/auth/google/callback';
    }

    /**
     * Build a deliberately safe, secret-free status payload.
     *
     * @return array<string, mixed>
     */
    public function publicStatus(): array
    {
        $configuration = $this->current();
        $environment = $this->activeEnvironment();

        return [
            'enabled' => $configuration->enabled,
            'environment' => $environment,
            'web_enabled' => $this->webLoginEnabled($environment),
            'api_enabled' => $this->apiLoginEnabled($environment),
            'registration_enabled' => $configuration->registration_enabled,
            'id_token_max_age_minutes' => (int) $configuration->max_id_token_age_minutes,
        ];
    }
}

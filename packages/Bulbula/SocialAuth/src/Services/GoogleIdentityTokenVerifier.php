<?php

namespace Bulbula\SocialAuth\Services;

use Google\AccessToken\Verify;
use GuzzleHttp\Client;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Throwable;

class GoogleIdentityTokenVerifier
{
    /**
     * Verify a Google ID token against configured client IDs and freshness policy.
     *
     * Google\AccessToken\Verify validates Google's signature, issuer, expiry, and
     * the audience it is given. This wrapper additionally requires an aud claim
     * (the upstream verifier accepts a missing aud), enforces an exact audience
     * match, and applies the configured maximum token age.
     *
     * @param array<int, string> $allowedAudiences
     * @return array<string, mixed>|null
     */
    public function verify(string $idToken, array $allowedAudiences, int $maxAgeMinutes): ?array
    {
        $allowedAudiences = array_values(array_unique(array_filter(
            array_map(static fn ($audience) => trim((string) $audience), $allowedAudiences),
            static fn (string $audience) => $audience !== ''
        )));

        if ($idToken === '' || $allowedAudiences === []) {
            return null;
        }

        $maxAgeMinutes = max(1, min(60, $maxAgeMinutes));
        $verifier = $this->makeVerifier();
        $claims = false;

        foreach ($allowedAudiences as $audience) {
            try {
                $claims = $verifier->verifyIdToken($idToken, $audience);
            } catch (Throwable $exception) {
                // Certificate-fetch/network failures fail closed. Do not log the token or exception text.
                throw new GoogleIdentityVerificationUnavailable(previous: $exception);
            }

            if (is_array($claims)) {
                break;
            }
        }

        if (! is_array($claims)) {
            return null;
        }

        $now = now()->timestamp;
        $audience = $claims['aud'] ?? null;
        $issuer = $claims['iss'] ?? null;
        $subject = $claims['sub'] ?? null;
        $issuedAt = filter_var($claims['iat'] ?? null, FILTER_VALIDATE_INT);
        $expiresAt = filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT);

        if (! is_string($audience) || ! in_array($audience, $allowedAudiences, true)) {
            return null;
        }

        if (! is_string($issuer) || ! in_array($issuer, [
            Verify::OAUTH2_ISSUER,
            Verify::OAUTH2_ISSUER_HTTPS,
        ], true)) {
            return null;
        }

        if (! is_string($subject) || trim($subject) === '') {
            return null;
        }

        if ($issuedAt === false || $expiresAt === false || $expiresAt <= $now) {
            return null;
        }

        if ($issuedAt > $now + 60 || $issuedAt < $now - ($maxAgeMinutes * 60)) {
            return null;
        }

        return $claims;
    }

    protected function makeVerifier(): Verify
    {
        $http = new Client([
            'timeout' => 5,
            'connect_timeout' => 3,
            'allow_redirects' => false,
            'http_errors' => true,
        ]);

        $cache = new FilesystemAdapter(
            namespace: 'google-id-token-certificates',
            defaultLifetime: 0,
            directory: storage_path('framework/cache/data')
        );

        return new Verify($http, $cache);
    }
}

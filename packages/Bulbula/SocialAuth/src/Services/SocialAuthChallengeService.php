<?php

namespace Bulbula\SocialAuth\Services;

use Bulbula\SocialAuth\Models\SocialAuthChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialAuthChallengeService
{
    public const GOOGLE_LOGIN_NONCE = 'google_login_nonce';
    public const GOOGLE_LINK_NONCE = 'google_link_nonce';
    public const GOOGLE_TWO_FACTOR = 'google_two_factor';

    /**
     * Issue a short-lived, one-use nonce. Only its SHA-256 hash is persisted.
     *
     * @return array{token:string,expires_in:int}
     */
    public function issueNonce(string $type, ?int $userId = null): array
    {
        if (! in_array($type, [self::GOOGLE_LOGIN_NONCE, self::GOOGLE_LINK_NONCE], true)) {
            throw new \InvalidArgumentException('Unsupported social authentication challenge type.');
        }

        $this->deleteExpired();

        $token = Str::random(64);
        $expiresIn = 300;

        SocialAuthChallenge::query()->create([
            'type' => $type,
            'token_hash' => hash('sha256', $token),
            'user_id' => $userId,
            'expires_at' => now()->addSeconds($expiresIn),
        ]);

        return ['token' => $token, 'expires_in' => $expiresIn];
    }

    /** @return array{token:string,expires_in:int} */
    public function issueTwoFactor(int $userId, string $deviceName): array
    {
        $this->deleteExpired();

        $token = Str::random(64);
        $expiresIn = 300;

        SocialAuthChallenge::query()->create([
            'type' => self::GOOGLE_TWO_FACTOR,
            'token_hash' => hash('sha256', $token),
            'user_id' => $userId,
            'device_name' => $deviceName,
            'attempts' => 0,
            'expires_at' => now()->addSeconds($expiresIn),
        ]);

        return ['token' => $token, 'expires_in' => $expiresIn];
    }

    public function findNonce(string $type, string $token, ?int $userId = null): ?SocialAuthChallenge
    {
        $query = SocialAuthChallenge::query()
            ->where('type', $type)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now());

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    public function consumeNonce(string $type, string $token, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($type, $token, $userId) {
            $query = SocialAuthChallenge::query()
                ->where('type', $type)
                ->where('token_hash', hash('sha256', $token))
                ->where('expires_at', '>', now())
                ->lockForUpdate();

            if ($userId !== null) {
                $query->where('user_id', $userId);
            }

            $challenge = $query->first();

            if (! $challenge) {
                return false;
            }

            $challenge->delete();

            return true;
        });
    }

    public function deleteExpired(): void
    {
        SocialAuthChallenge::query()->where('expires_at', '<=', now())->delete();
    }
}

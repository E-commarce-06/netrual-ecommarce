<?php

namespace Bulbula\SocialAuth\Services;

use App\Models\User;
use Bulbula\SocialAuth\Models\SocialAuthAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialAuthAccountService
{
    /**
     * Find an already-linked identity or create a new customer account.
     * Existing users are never linked by email alone.
     *
     * @return array{status:string,user:?User}
     */
    public function resolveOrCreateGoogleUser(
        string $subject,
        ?string $email,
        ?string $name,
        bool $emailVerified,
        bool $registrationEnabled
    ): array {
        $subject = trim($subject);

        if ($subject === '') {
            return ['status' => 'invalid_identity', 'user' => null];
        }

        $identity = $this->findIdentity('google', $subject);

        if ($identity) {
            return ['status' => 'linked', 'user' => $identity->user];
        }

        if (! $registrationEnabled) {
            return ['status' => 'registration_disabled', 'user' => null];
        }

        $normalizedEmail = Str::lower(trim((string) $email));

        if ($normalizedEmail === ''
            || strlen($normalizedEmail) > 255
            || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ['status' => 'missing_email', 'user' => null];
        }

        if ($this->userWithEmailExists($normalizedEmail)) {
            return ['status' => 'email_conflict', 'user' => null];
        }

        $displayName = trim((string) $name);
        if ($displayName === '') {
            $displayName = Str::before($normalizedEmail, '@');
        }
        if ($displayName === '') {
            $displayName = 'Customer';
        }

        try {
            return DB::transaction(function () use ($subject, $normalizedEmail, $displayName, $emailVerified) {
                $identity = SocialAuthAccount::query()
                    ->with('user')
                    ->where('provider', 'google')
                    ->where('provider_user_id', $subject)
                    ->lockForUpdate()
                    ->first();

                if ($identity) {
                    return ['status' => 'linked', 'user' => $identity->user];
                }

                $existingUser = User::query()
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->lockForUpdate()
                    ->first();

                if ($existingUser) {
                    return ['status' => 'email_conflict', 'user' => null];
                }

                $user = User::query()->create([
                    'name' => Str::limit($displayName, 255, ''),
                    'email' => $normalizedEmail,
                    'role' => 'customer',
                    'is_active' => true,
                    // The local schema requires a password. This random password is never returned or logged.
                    'password' => Hash::make(Str::random(96)),
                    'email_verified_at' => $emailVerified ? now() : null,
                ]);

                SocialAuthAccount::query()->create([
                    'user_id' => $user->getKey(),
                    'provider' => 'google',
                    'provider_user_id' => $subject,
                ]);

                return ['status' => 'created', 'user' => $user];
            });
        } catch (QueryException $exception) {
            // Unique indexes arbitrate concurrent first-time sign-ins. Recover only when
            // the winning row is now visible; otherwise leave the database error intact.
            $identity = $this->findIdentity('google', $subject);

            if ($identity) {
                return ['status' => 'linked', 'user' => $identity->user];
            }

            if ($this->userWithEmailExists($normalizedEmail)) {
                return ['status' => 'email_conflict', 'user' => null];
            }

            throw $exception;
        }
    }

    /**
     * Explicitly attach a Google subject to an authenticated application user.
     *
     * @return string linked|already_linked|owned_by_another_user|provider_already_linked
     */
    public function linkGoogleIdentity(User $user, string $subject): string
    {
        $subject = trim($subject);

        if ($subject === '') {
            return 'invalid_identity';
        }

        try {
            return DB::transaction(function () use ($user, $subject) {
                $identity = SocialAuthAccount::query()
                    ->where('provider', 'google')
                    ->where('provider_user_id', $subject)
                    ->lockForUpdate()
                    ->first();

                if ($identity) {
                    return (int) $identity->user_id === (int) $user->getKey()
                        ? 'already_linked'
                        : 'owned_by_another_user';
                }

                $userIdentity = SocialAuthAccount::query()
                    ->where('provider', 'google')
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($userIdentity) {
                    return 'provider_already_linked';
                }

                SocialAuthAccount::query()->create([
                    'user_id' => $user->getKey(),
                    'provider' => 'google',
                    'provider_user_id' => $subject,
                ]);

                return 'linked';
            });
        } catch (QueryException $exception) {
            $identity = $this->findIdentity('google', $subject);

            if ($identity) {
                return (int) $identity->user_id === (int) $user->getKey()
                    ? 'already_linked'
                    : 'owned_by_another_user';
            }

            if (SocialAuthAccount::query()
                ->where('provider', 'google')
                ->where('user_id', $user->getKey())
                ->exists()) {
                return 'provider_already_linked';
            }

            throw $exception;
        }
    }

    public function unlinkGoogleIdentity(User $user): bool
    {
        return SocialAuthAccount::query()
            ->where('provider', 'google')
            ->where('user_id', $user->getKey())
            ->delete() > 0;
    }

    public function hasGoogleIdentity(User $user): bool
    {
        return SocialAuthAccount::query()
            ->where('provider', 'google')
            ->where('user_id', $user->getKey())
            ->exists();
    }

    private function findIdentity(string $provider, string $subject): ?SocialAuthAccount
    {
        return SocialAuthAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $subject)
            ->first();
    }

    private function userWithEmailExists(string $email): bool
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();
    }
}

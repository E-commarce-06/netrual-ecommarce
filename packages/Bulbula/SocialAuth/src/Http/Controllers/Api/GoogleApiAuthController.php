<?php

namespace Bulbula\SocialAuth\Http\Controllers\Api;

use App\Models\User;
use Bulbula\API\Helpers\ApiResponse;
use Bulbula\API\Http\Resources\UserResource;
use Bulbula\SocialAuth\Models\SocialAuthChallenge;
use Bulbula\SocialAuth\Services\GoogleAuthSettings;
use Bulbula\SocialAuth\Services\GoogleIdentityTokenVerifier;
use Bulbula\SocialAuth\Services\GoogleIdentityVerificationUnavailable;
use Bulbula\SocialAuth\Services\SocialAuthAccountService;
use Bulbula\SocialAuth\Services\SocialAuthChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Throwable;

class GoogleApiAuthController
{
    public function __construct(
        protected GoogleAuthSettings $settings,
        protected GoogleIdentityTokenVerifier $tokenVerifier,
        protected SocialAuthAccountService $accounts,
        protected SocialAuthChallengeService $challenges
    ) {
    }

    public function configuration(): JsonResponse
    {
        $configuration = $this->settings->current();
        $environment = $this->settings->activeEnvironment();

        return ApiResponse::success([
            'enabled' => $this->settings->apiLoginEnabled($environment),
            'registration_enabled' => $this->settings->apiRegistrationEnabled($environment),
            'environment' => $environment,
            'nonce_required' => true,
            'id_token_max_age_minutes' => (int) $configuration->max_id_token_age_minutes,
            'two_factor_challenge_supported' => Features::enabled(Features::twoFactorAuthentication()),
        ], 'Google authentication configuration');
    }

    public function issueLoginNonce(): JsonResponse
    {
        if (! $this->settings->apiLoginEnabled()) {
            return ApiResponse::notFound('Google sign-in is unavailable.', 'GOOGLE_AUTH_DISABLED');
        }

        $challenge = $this->challenges->issueNonce(SocialAuthChallengeService::GOOGLE_LOGIN_NONCE);

        return ApiResponse::success([
            'nonce' => $challenge['token'],
            'expires_in' => $challenge['expires_in'],
        ], 'Google sign-in challenge created');
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_token' => ['required', 'string', 'max:8192'],
            'nonce' => ['required', 'string', 'min:16', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator);
        }

        if (! $this->settings->apiLoginEnabled()) {
            return ApiResponse::notFound('Google sign-in is unavailable.', 'GOOGLE_AUTH_DISABLED');
        }

        $nonce = (string) $request->input('nonce');
        if (! $this->challenges->findNonce(SocialAuthChallengeService::GOOGLE_LOGIN_NONCE, $nonce)) {
            return ApiResponse::unauthorized('The Google sign-in challenge is invalid or expired.');
        }

        $claims = $this->verifyToken((string) $request->input('id_token'));

        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        if (! $this->tokenHasNonce($claims, $nonce)) {
            return ApiResponse::unauthorized('The Google sign-in challenge is invalid or expired.');
        }

        if (! $this->challenges->consumeNonce(SocialAuthChallengeService::GOOGLE_LOGIN_NONCE, $nonce)) {
            return ApiResponse::unauthorized('The Google sign-in challenge is invalid or expired.');
        }

        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $resolution = $this->accounts->resolveOrCreateGoogleUser(
            subject: (string) $claims['sub'],
            email: isset($claims['email']) ? (string) $claims['email'] : null,
            name: isset($claims['name']) ? (string) $claims['name'] : null,
            emailVerified: $emailVerified,
            registrationEnabled: $this->settings->apiRegistrationEnabled()
        );

        if (! in_array($resolution['status'], ['created', 'linked'], true) || ! $resolution['user']) {
            return $this->resolutionFailure($resolution['status']);
        }

        /** @var User $user */
        $user = $resolution['user'];

        if ($this->isAdmin($user)) {
            return ApiResponse::error(
                'Google sign-in is only available for customer accounts.',
                null,
                403,
                'GOOGLE_AUTH_NOT_AVAILABLE_FOR_ADMIN'
            );
        }

        if (! $user->is_active) {
            return ApiResponse::error('This account is currently unavailable.', null, 403, 'ACCOUNT_DISABLED');
        }

        $deviceName = trim((string) $request->input('device_name', ''));
        if ($deviceName === '') {
            $deviceName = (string) config('bulbula-api.token.name', 'mobile-app');
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $challenge = $this->challenges->issueTwoFactor((int) $user->getKey(), $deviceName);

            return ApiResponse::success([
                'two_factor_required' => true,
                'challenge_token' => $challenge['token'],
                'expires_in' => $challenge['expires_in'],
            ], 'Two-factor authentication is required.', 202);
        }

        return $this->tokenResponse($user, $deviceName, $resolution['status'] === 'created');
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => ['required', 'string', 'min:32', 'max:255'],
            'code' => ['required', 'string', 'min:6', 'max:64'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator);
        }

        $tokenHash = hash('sha256', (string) $request->input('challenge_token'));

        $result = DB::transaction(function () use ($tokenHash, $request) {
            $challenge = SocialAuthChallenge::query()
                ->where('type', SocialAuthChallengeService::GOOGLE_TWO_FACTOR)
                ->where('token_hash', $tokenHash)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $challenge) {
                return ['status' => 'expired'];
            }

            if ((int) $challenge->attempts >= 5) {
                $challenge->delete();

                return ['status' => 'expired'];
            }

            $user = User::query()->lockForUpdate()->find($challenge->user_id);

            if (! $user || $this->isAdmin($user) || ! $user->is_active) {
                $challenge->delete();

                return ['status' => 'unavailable'];
            }

            if (! Features::enabled(Features::twoFactorAuthentication()) || ! $user->hasEnabledTwoFactorAuthentication()) {
                $challenge->delete();

                return ['status' => 'unavailable'];
            }

            if (! $this->verifySecondFactor($user, (string) $request->input('code'))) {
                $challenge->attempts = (int) $challenge->attempts + 1;
                $locked = $challenge->attempts >= 5;
                $challenge->save();

                if ($locked) {
                    $challenge->delete();
                }

                return ['status' => $locked ? 'locked' : 'invalid'];
            }

            $deviceName = trim((string) $challenge->device_name);
            if ($deviceName === '') {
                $deviceName = (string) config('bulbula-api.token.name', 'mobile-app');
            }

            $plainTextToken = $this->createToken($user, $deviceName);
            $challenge->delete();

            return [
                'status' => 'verified',
                'user' => $user,
                'token' => $plainTextToken,
                'device_name' => $deviceName,
            ];
        });

        if ($result['status'] !== 'verified') {
            $message = $result['status'] === 'invalid'
                ? 'The two-factor code is invalid.'
                : 'The two-factor challenge is invalid or expired.';

            return ApiResponse::unauthorized($message);
        }

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => $this->tokenExpirationSeconds(),
        ], 'Login successful');
    }

    public function issueLinkNonce(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator);
        }

        if (! $this->settings->apiLoginEnabled()) {
            return ApiResponse::notFound('Google account linking is unavailable.', 'GOOGLE_AUTH_DISABLED');
        }

        $user = $request->user();

        if (! $user instanceof User || $this->isAdmin($user)) {
            return ApiResponse::error('Google account linking is only available for customer accounts.', null, 403, 'FORBIDDEN');
        }

        if (! $user->is_active) {
            return ApiResponse::error('This account is currently unavailable.', null, 403, 'ACCOUNT_DISABLED');
        }

        if (! Hash::check((string) $request->input('current_password'), (string) $user->getAuthPassword())) {
            return ApiResponse::error('The current password is incorrect.', null, 403, 'INVALID_PASSWORD');
        }

        if ($this->accounts->hasGoogleIdentity($user)) {
            return ApiResponse::error('A Google account is already connected.', null, 409, 'GOOGLE_ALREADY_LINKED');
        }

        $challenge = $this->challenges->issueNonce(
            SocialAuthChallengeService::GOOGLE_LINK_NONCE,
            (int) $user->getKey()
        );

        return ApiResponse::success([
            'nonce' => $challenge['token'],
            'expires_in' => $challenge['expires_in'],
        ], 'Google account-link challenge created');
    }

    public function link(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_token' => ['required', 'string', 'max:8192'],
            'nonce' => ['required', 'string', 'min:16', 'max:255'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator);
        }

        $user = $request->user();

        if (! $user instanceof User || $this->isAdmin($user)) {
            return ApiResponse::error('Google account linking is only available for customer accounts.', null, 403, 'FORBIDDEN');
        }

        if (! $user->is_active) {
            return ApiResponse::error('This account is currently unavailable.', null, 403, 'ACCOUNT_DISABLED');
        }

        if (! $this->settings->apiLoginEnabled()) {
            return ApiResponse::notFound('Google account linking is unavailable.', 'GOOGLE_AUTH_DISABLED');
        }

        $nonce = (string) $request->input('nonce');
        if (! $this->challenges->findNonce(SocialAuthChallengeService::GOOGLE_LINK_NONCE, $nonce, (int) $user->getKey())) {
            return ApiResponse::unauthorized('The Google account-link challenge is invalid or expired.');
        }

        $claims = $this->verifyToken((string) $request->input('id_token'));

        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        if (! $this->tokenHasNonce($claims, $nonce)) {
            return ApiResponse::unauthorized('The Google account-link challenge is invalid or expired.');
        }

        if (! $this->challenges->consumeNonce(
            SocialAuthChallengeService::GOOGLE_LINK_NONCE,
            $nonce,
            (int) $user->getKey()
        )) {
            return ApiResponse::unauthorized('The Google account-link challenge is invalid or expired.');
        }

        $status = $this->accounts->linkGoogleIdentity($user, (string) $claims['sub']);

        return match ($status) {
            'linked', 'already_linked' => ApiResponse::success(null, 'Google account connected.'),
            'owned_by_another_user' => ApiResponse::error('That Google account is already connected to another account.', null, 409, 'GOOGLE_IDENTITY_IN_USE'),
            'provider_already_linked' => ApiResponse::error('A different Google account is already connected.', null, 409, 'GOOGLE_ALREADY_LINKED'),
            default => ApiResponse::error('Google could not be connected.', null, 422, 'GOOGLE_LINK_FAILED'),
        };
    }

    public function unlink(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator);
        }

        $user = $request->user();

        if (! $user instanceof User || $this->isAdmin($user)) {
            return ApiResponse::error('Google account management is only available for customer accounts.', null, 403, 'FORBIDDEN');
        }

        if (! Hash::check((string) $request->input('current_password'), (string) $user->getAuthPassword())) {
            return ApiResponse::error('The current password is incorrect.', null, 403, 'INVALID_PASSWORD');
        }

        if (! $this->accounts->unlinkGoogleIdentity($user)) {
            return ApiResponse::notFound('No Google account is connected.', 'GOOGLE_NOT_LINKED');
        }

        return ApiResponse::success(null, 'Google account disconnected.');
    }

    /** @return array<string, mixed>|JsonResponse */
    private function verifyToken(string $idToken): array|JsonResponse
    {
        $profile = $this->settings->profile();
        $configuration = $this->settings->current();

        try {
            $claims = $this->tokenVerifier->verify(
                $idToken,
                $profile['api_audiences'],
                (int) $configuration->max_id_token_age_minutes
            );
        } catch (GoogleIdentityVerificationUnavailable $exception) {
            Log::warning('Google ID-token verification service is unavailable.', [
                'exception' => $exception::class,
            ]);

            return ApiResponse::error(
                'Google sign-in is temporarily unavailable. Please try again.',
                null,
                503,
                'GOOGLE_VERIFICATION_UNAVAILABLE'
            );
        } catch (Throwable $exception) {
            Log::warning('Google ID-token verification failed.', [
                'exception' => $exception::class,
            ]);

            return ApiResponse::unauthorized('The Google ID token is invalid or expired.');
        }

        if (! is_array($claims)) {
            return ApiResponse::unauthorized('The Google ID token is invalid or expired.');
        }

        return $claims;
    }

    /** @param array<string, mixed> $claims */
    private function tokenHasNonce(array $claims, string $nonce): bool
    {
        $claimNonce = $claims['nonce'] ?? null;

        return is_string($claimNonce) && $claimNonce !== '' && hash_equals($nonce, $claimNonce);
    }

    private function resolutionFailure(string $status): JsonResponse
    {
        return match ($status) {
            'email_conflict' => ApiResponse::error(
                'This Google account is not linked. Sign in to your existing account, then explicitly connect Google in account settings.',
                null,
                409,
                'ACCOUNT_LINK_REQUIRED'
            ),
            'registration_disabled' => ApiResponse::error('Google sign-up is currently unavailable.', null, 403, 'GOOGLE_REGISTRATION_DISABLED'),
            'missing_email' => ApiResponse::error('Google did not provide an email address needed to create an account.', null, 422, 'GOOGLE_EMAIL_REQUIRED'),
            default => ApiResponse::unauthorized('The Google account could not be authenticated.'),
        };
    }

    private function tokenResponse(User $user, string $deviceName, bool $created): JsonResponse
    {
        $token = $this->createToken($user, $deviceName);

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->tokenExpirationSeconds(),
        ], $created ? 'Registration successful.' : 'Login successful.', $created ? 201 : 200);
    }

    private function createToken(User $user, string $deviceName): string
    {
        $expiresAt = now()->addMinutes($this->tokenExpirationMinutes());

        return $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;
    }

    private function tokenExpirationMinutes(): int
    {
        return max(1, (int) config('bulbula-api.token.expiration', 1440));
    }

    private function tokenExpirationSeconds(): int
    {
        return $this->tokenExpirationMinutes() * 60;
    }

    private function verifySecondFactor(User $user, string $code): bool
    {
        if (preg_match('/^\d{6,8}$/', $code) === 1) {
            try {
                $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

                if (app(TwoFactorAuthenticationProvider::class)->verify($secret, $code)) {
                    return true;
                }
            } catch (Throwable) {
                // Continue to recovery-code validation; do not expose decryption details.
            }
        }

        try {
            foreach ($user->recoveryCodes() as $recoveryCode) {
                if (is_string($recoveryCode) && hash_equals($recoveryCode, $code)) {
                    $user->replaceRecoveryCode($recoveryCode);

                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    private function isAdmin(User $user): bool
    {
        return (string) ($user->role ?? '') === 'admin'
            || (method_exists($user, 'isAdmin') && (bool) $user->isAdmin());
    }
}

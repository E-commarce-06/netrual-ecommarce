<?php

namespace Bulbula\SocialAuth\Http\Controllers\Auth;

use App\Models\User;
use Bulbula\SocialAuth\Services\GoogleAuthSettings;
use Bulbula\SocialAuth\Services\GoogleOAuthProviderFactory;
use Bulbula\SocialAuth\Services\SocialAuthAccountService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Features;
use Throwable;

class GoogleWebAuthController
{
    public function __construct(
        protected GoogleAuthSettings $settings,
        protected GoogleOAuthProviderFactory $providers,
        protected SocialAuthAccountService $accounts
    ) {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->settings->webLoginEnabled()) {
            return redirect()->route('login')->with('error', 'Google sign-in is currently unavailable.');
        }

        $environment = $this->settings->activeEnvironment();
        $request->session()->put([
            'social_auth.google.action' => 'login',
            'social_auth.google.environment' => $environment,
        ]);
        $request->session()->forget('social_auth.google.owner_id');

        try {
            return $this->providers->make($environment)->redirect();
        } catch (Throwable $exception) {
            Log::warning('Google OAuth redirect could not be started.', [
                'exception' => $exception::class,
            ]);

            $request->session()->forget([
                'social_auth.google.action',
                'social_auth.google.environment',
                'social_auth.google.owner_id',
            ]);

            return redirect()->route('login')->with('error', 'Google sign-in could not be started. Please try again.');
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $action = $request->session()->pull('social_auth.google.action');
        $environment = $request->session()->pull('social_auth.google.environment');
        $ownerId = $request->session()->pull('social_auth.google.owner_id');

        if (! in_array($action, ['login', 'link'], true) || ! in_array($environment, ['test', 'live'], true)) {
            return redirect()->route('login')->with('error', 'That Google sign-in attempt expired. Please try again.');
        }

        if (! $this->settings->webLoginEnabled($environment)) {
            return $this->failure($action, 'Google sign-in is currently unavailable.');
        }

        try {
            $googleUser = $this->providers->make($environment)->user();
        } catch (Throwable $exception) {
            // Do not log OAuth codes, access tokens, provider responses, or exception messages.
            Log::notice('Google OAuth callback was rejected.', [
                'exception' => $exception::class,
            ]);

            return $this->failure($action, 'Google sign-in could not be completed. Please try again.');
        }

        $subject = trim((string) $googleUser->getId());

        if ($subject === '') {
            return $this->failure($action, 'Google did not return a usable account identifier.');
        }

        if ($action === 'link') {
            $user = Auth::guard('web')->user();

            if (! ($user instanceof User) || (int) $user->getKey() !== (int) $ownerId || $this->isAdmin($user) || ! $user->is_active) {
                return redirect()->route('login')->with('error', 'Sign in to the same customer account before connecting Google.');
            }

            $status = $this->accounts->linkGoogleIdentity($user, $subject);

            return match ($status) {
                'linked', 'already_linked' => redirect()
                    ->route('shop.account.security.social.index')
                    ->with('success', 'Google has been connected to your account.'),
                'owned_by_another_user' => redirect()
                    ->route('shop.account.security.social.index')
                    ->with('error', 'That Google account is already connected to another account.'),
                'provider_already_linked' => redirect()
                    ->route('shop.account.security.social.index')
                    ->with('error', 'A different Google account is already connected.'),
                default => redirect()
                    ->route('shop.account.security.social.index')
                    ->with('error', 'Google could not be connected. Please try again.'),
            };
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route('shop.account.security.social.index')
                ->with('info', 'You are already signed in. Use Connected accounts to link Google.');
        }

        $raw = $googleUser->getRaw();
        $emailVerified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $resolution = $this->accounts->resolveOrCreateGoogleUser(
            subject: $subject,
            email: $googleUser->getEmail(),
            name: $googleUser->getName(),
            emailVerified: $emailVerified,
            registrationEnabled: $this->settings->webRegistrationEnabled($environment)
        );

        if (! in_array($resolution['status'], ['created', 'linked'], true) || ! $resolution['user']) {
            return $this->resolutionFailure($resolution['status']);
        }

        /** @var User $user */
        $user = $resolution['user'];

        if ($this->isAdmin($user)) {
            return redirect()->route('login')->with('error', 'Google sign-in is only available for customer accounts.');
        }

        if (! $user->is_active) {
            return redirect()->route('login')->with('error', 'This account is currently unavailable. Please contact support.');
        }

        if ($resolution['status'] === 'created') {
            event(new Registered($user));
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            return to_route('two-factor.login');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('shop.account.dashboard'));
    }

    private function resolutionFailure(string $status): RedirectResponse
    {
        $message = match ($status) {
            'email_conflict' => 'An account already uses this email. Sign in to that account first, then connect Google from Connected accounts. Accounts are never linked by email alone.',
            'registration_disabled' => 'Google sign-up is currently unavailable. If you already have an account, sign in with your usual method.',
            'missing_email' => 'Google did not provide an email address needed to create an account.',
            default => 'Google sign-in could not be completed. Please try again.',
        };

        return redirect()->route('login')->with('error', $message);
    }

    private function failure(string $action, string $message): RedirectResponse
    {
        if ($action === 'link' && Auth::guard('web')->check()) {
            return redirect()->route('shop.account.security.social.index')->with('error', $message);
        }

        return redirect()->route('login')->with('error', $message);
    }

    private function isAdmin(User $user): bool
    {
        return (string) ($user->role ?? '') === 'admin'
            || (method_exists($user, 'isAdmin') && (bool) $user->isAdmin());
    }
}

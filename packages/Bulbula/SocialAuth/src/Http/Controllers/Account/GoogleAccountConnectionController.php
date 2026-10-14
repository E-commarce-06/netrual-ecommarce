<?php

namespace Bulbula\SocialAuth\Http\Controllers\Account;

use App\Models\User;
use Bulbula\Core\Services\ThemeViewResolver;
use Bulbula\SocialAuth\Services\GoogleAuthSettings;
use Bulbula\SocialAuth\Services\GoogleOAuthProviderFactory;
use Bulbula\SocialAuth\Services\SocialAuthAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Response;
use Throwable;

class GoogleAccountConnectionController
{
    public function __construct(
        protected ThemeViewResolver $themeResolver,
        protected GoogleAuthSettings $settings,
        protected GoogleOAuthProviderFactory $providers,
        protected SocialAuthAccountService $accounts
    ) {
    }

    public function index(): Response
    {
        $user = Auth::guard('web')->user();

        abort_unless($user instanceof User && ! $this->isAdmin($user), 403);

        return $this->themeResolver->inertia('Account/Security/ConnectedAccounts', [
            'google' => [
                'linked' => $this->accounts->hasGoogleIdentity($user),
                'available' => $this->settings->webLoginEnabled(),
            ],
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        abort_unless($user instanceof User && ! $this->isAdmin($user), 403);

        if (! $user->is_active) {
            return back()->with('error', 'This account is currently unavailable.');
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except('current_password'));
        }

        if (! Hash::check((string) $request->input('current_password'), (string) $user->getAuthPassword())) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.',
            ])->withInput($request->except('current_password'));
        }

        if (! $this->settings->webLoginEnabled()) {
            return back()->with('error', 'Google sign-in is currently unavailable.');
        }

        if ($this->accounts->hasGoogleIdentity($user)) {
            return back()->with('info', 'A Google account is already connected.');
        }

        $environment = $this->settings->activeEnvironment();
        $request->session()->put([
            'social_auth.google.action' => 'link',
            'social_auth.google.environment' => $environment,
            'social_auth.google.owner_id' => $user->getKey(),
        ]);

        try {
            return $this->providers->make($environment)->redirect();
        } catch (Throwable $exception) {
            Log::warning('Google account linking could not be started.', [
                'exception' => $exception::class,
            ]);

            $request->session()->forget([
                'social_auth.google.action',
                'social_auth.google.environment',
                'social_auth.google.owner_id',
            ]);

            return back()->with('error', 'Google could not be connected. Please try again.');
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        abort_unless($user instanceof User && ! $this->isAdmin($user), 403);

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except('current_password'));
        }

        if (! Hash::check((string) $request->input('current_password'), (string) $user->getAuthPassword())) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.',
            ])->withInput($request->except('current_password'));
        }

        if (! $this->accounts->unlinkGoogleIdentity($user)) {
            return back()->with('info', 'No Google account is currently connected.');
        }

        return back()->with('success', 'Google has been disconnected.');
    }

    private function isAdmin(User $user): bool
    {
        return (string) ($user->role ?? '') === 'admin'
            || (method_exists($user, 'isAdmin') && (bool) $user->isAdmin());
    }
}

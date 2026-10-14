<?php

namespace Bulbula\SocialAuth\Http\Controllers\Admin;

use Bulbula\SocialAuth\Models\SocialAuthConfiguration;
use Bulbula\SocialAuth\Services\GoogleAuthSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GoogleAuthenticationSettingsController
{
    public function __construct(protected GoogleAuthSettings $settings)
    {
    }

    public function index(): Response
    {
        $configuration = $this->settings->current();
        $testProfile = $this->settings->profile('test');
        $liveProfile = $this->settings->profile('live');
        $activeEnvironment = $this->settings->activeEnvironment();

        return Inertia::render('Admin/System/Authentication/Google', [
            'settings' => [
                'enabled' => (bool) $configuration->enabled,
                'active_environment' => $activeEnvironment,
                'web_enabled' => (bool) $configuration->web_enabled,
                'api_enabled' => (bool) $configuration->api_enabled,
                'registration_enabled' => (bool) $configuration->registration_enabled,
                'max_id_token_age_minutes' => (int) $configuration->max_id_token_age_minutes,
                'test_client_id' => (string) ($configuration->test_client_id ?? ''),
                'test_client_secret_configured' => $testProfile['client_secret'] !== '',
                'test_api_audiences_text' => implode("\n", $testProfile['api_audiences']),
                'live_client_id' => (string) ($configuration->live_client_id ?? ''),
                'live_client_secret_configured' => $liveProfile['client_secret'] !== '',
                'live_api_audiences_text' => implode("\n", $liveProfile['api_audiences']),
                'active_web_ready' => $this->settings->webLoginEnabled($activeEnvironment),
                'active_api_ready' => $this->settings->apiLoginEnabled($activeEnvironment),
            ],
            'callbackUrl' => $this->settings->callbackUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $configuration = $this->settings->current();
        $validator = Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
            'active_environment' => ['required', 'in:test,live'],
            'web_enabled' => ['required', 'boolean'],
            'api_enabled' => ['required', 'boolean'],
            'registration_enabled' => ['required', 'boolean'],
            'max_id_token_age_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'test_client_id' => ['nullable', 'string', 'max:255'],
            'test_client_secret' => ['nullable', 'string', 'max:2048'],
            'clear_test_client_secret' => ['nullable', 'boolean'],
            'test_api_audiences_text' => ['nullable', 'string', 'max:5000'],
            'live_client_id' => ['nullable', 'string', 'max:255'],
            'live_client_secret' => ['nullable', 'string', 'max:2048'],
            'clear_live_client_secret' => ['nullable', 'boolean'],
            'live_api_audiences_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $validator->after(function ($validator) use ($request, $configuration) {
            foreach (['test', 'live'] as $environment) {
                if ($request->boolean("clear_{$environment}_client_secret")
                    && trim((string) $request->input("{$environment}_client_secret", '')) !== '') {
                    $validator->errors()->add(
                        "{$environment}_client_secret",
                        'Enter a new secret or choose to clear the saved secret, not both.'
                    );
                }
            }

            if (! $request->boolean('enabled')) {
                return;
            }

            $environment = (string) $request->input('active_environment');
            if (! in_array($environment, ['test', 'live'], true)) {
                return;
            }

            if ($request->boolean('web_enabled')) {
                $clientId = trim((string) $request->input("{$environment}_client_id", ''));
                $clientSecret = $this->effectiveSecret($request, $configuration, $environment);

                if ($clientId === '' || $clientSecret === '') {
                    $validator->errors()->add(
                        'active_environment',
                        'The active web profile needs a Google OAuth client ID and client secret before web sign-in can be enabled.'
                    );
                }
            }

            if ($request->boolean('api_enabled') && $this->audiencesFromText(
                (string) $request->input("{$environment}_api_audiences_text", '')
            ) === []) {
                $validator->errors()->add(
                    "{$environment}_api_audiences_text",
                    'Add at least one Google OAuth client ID audience for the active API profile.'
                );
            }
        });

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except(['test_client_secret', 'live_client_secret']));
        }

        $data = $validator->validated();

        try {
            DB::transaction(function () use ($configuration, $data) {
                $configuration->enabled = (bool) $data['enabled'];
                $configuration->active_environment = $data['active_environment'];
                $configuration->web_enabled = (bool) $data['web_enabled'];
                $configuration->api_enabled = (bool) $data['api_enabled'];
                $configuration->registration_enabled = (bool) $data['registration_enabled'];
                $configuration->max_id_token_age_minutes = (int) $data['max_id_token_age_minutes'];

                foreach (['test', 'live'] as $environment) {
                    $clientIdField = "{$environment}_client_id";
                    $secretField = "{$environment}_client_secret";
                    $audiencesField = "{$environment}_api_audiences";
                    $newClientId = trim((string) ($data[$clientIdField] ?? ''));
                    $oldClientId = trim((string) ($configuration->getAttribute($clientIdField) ?? ''));
                    $clientIdChanged = $newClientId !== $oldClientId;

                    $configuration->{$clientIdField} = $newClientId !== '' ? $newClientId : null;
                    $configuration->{$audiencesField} = $this->audiencesFromText(
                        (string) ($data["{$environment}_api_audiences_text"] ?? '')
                    );

                    $newSecret = trim((string) ($data[$secretField] ?? ''));
                    $clearSecret = (bool) ($data["clear_{$environment}_client_secret"] ?? false);

                    if ($clearSecret || ($clientIdChanged && $newSecret === '')) {
                        $configuration->{$secretField} = null;
                    } elseif ($newSecret !== '') {
                        $configuration->{$secretField} = $newSecret;
                    }
                }

                $configuration->save();
            });
        } catch (Throwable $exception) {
            Log::error('Google authentication settings could not be saved.', [
                'exception' => $exception::class,
            ]);

            return back()
                ->with('error', 'Google authentication settings could not be saved. Please try again.')
                ->withInput($request->except(['test_client_secret', 'live_client_secret']));
        }

        return redirect()
            ->route('admin.system.authentication.google.index')
            ->with('success', 'Google authentication settings saved.');
    }

    private function effectiveSecret(Request $request, SocialAuthConfiguration $configuration, string $environment): string
    {
        $secretField = "{$environment}_client_secret";
        $newSecret = trim((string) $request->input($secretField, ''));

        if ($request->boolean("clear_{$environment}_client_secret")) {
            return '';
        }

        if ($newSecret !== '') {
            return $newSecret;
        }

        $newClientId = trim((string) $request->input("{$environment}_client_id", ''));
        $oldClientId = trim((string) ($configuration->getAttribute("{$environment}_client_id") ?? ''));

        if ($newClientId !== $oldClientId) {
            return '';
        }

        try {
            return (string) ($configuration->getAttribute($secretField) ?? '');
        } catch (Throwable) {
            return '';
        }
    }

    /** @return array<int, string> */
    private function audiencesFromText(string $value): array
    {
        $audiences = preg_split('/[\r\n,]+/', $value) ?: [];

        return array_values(array_unique(array_filter(
            array_map(static fn (string $audience) => trim($audience), $audiences),
            static fn (string $audience) => $audience !== ''
        )));
    }
}

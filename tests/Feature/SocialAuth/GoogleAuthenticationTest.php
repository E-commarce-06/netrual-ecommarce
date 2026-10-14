<?php

use App\Models\User;
use Bulbula\SocialAuth\Http\Controllers\Admin\GoogleAuthenticationSettingsController;
use Bulbula\SocialAuth\Models\SocialAuthAccount;
use Bulbula\SocialAuth\Models\SocialAuthConfiguration;
use Bulbula\SocialAuth\Services\GoogleIdentityTokenVerifier;
use Bulbula\SocialAuth\Services\SocialAuthAccountService;
use Bulbula\SocialAuth\Services\SocialAuthChallengeService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

beforeEach(function () {
    config(['app.key' => 'base64:' . base64_encode(str_repeat('a', 32))]);
});

function configureGoogleApiAuth(array $overrides = []): SocialAuthConfiguration
{
    return SocialAuthConfiguration::query()->create(array_merge([
        'provider' => 'google',
        'enabled' => true,
        'active_environment' => 'test',
        'web_enabled' => false,
        'api_enabled' => true,
        'registration_enabled' => true,
        'max_id_token_age_minutes' => 10,
        'test_api_audiences' => ['test-mobile-client-id'],
    ], $overrides));
}

test('Google identity with an existing email is never auto-linked', function () {
    $existingUser = User::factory()->create([
        'email' => 'customer@example.com',
        'role' => 'customer',
        'is_active' => true,
    ]);

    $result = app(SocialAuthAccountService::class)->resolveOrCreateGoogleUser(
        subject: 'google-subject-123',
        email: 'CUSTOMER@example.com',
        name: 'Customer',
        emailVerified: true,
        registrationEnabled: true,
    );

    expect($result['status'])->toBe('email_conflict')
        ->and($result['user'])->toBeNull()
        ->and(User::query()->count())->toBe(1)
        ->and(SocialAuthAccount::query()->count())->toBe(0)
        ->and($existingUser->fresh()->email)->toBe('customer@example.com');
});

test('a linked Google identity remains usable when new registration is disabled', function () {
    $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
    $accounts = app(SocialAuthAccountService::class);

    expect($accounts->linkGoogleIdentity($user, 'google-subject-456'))->toBe('linked');

    $result = $accounts->resolveOrCreateGoogleUser(
        subject: 'google-subject-456',
        email: $user->email,
        name: $user->name,
        emailVerified: false,
        registrationEnabled: false,
    );

    expect($result['status'])->toBe('linked')
        ->and((int) $result['user']->getKey())->toBe((int) $user->getKey());
});

test('storefront auth screens expose Google actions only when the active web profile is configured', function () {
    $loginPage = base_path('templates/storefront/general/bulbula-default/resources/views/pages/Auth/Login.vue');
    $registerPage = base_path('templates/storefront/general/bulbula-default/resources/views/pages/Auth/Register.vue');

    expect(file_get_contents($loginPage))
        ->toContain('v-if="googleSignInEnabled"')
        ->toContain('<GoogleAuthButton label="Continue with Google" />')
        ->and(file_get_contents($registerPage))
        ->toContain('v-if="googleSignUpEnabled"')
        ->toContain('<GoogleAuthButton label="Sign up with Google" />');

    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page->where('googleSignInEnabled', false));
    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page->where('googleSignUpEnabled', false));

    $configuration = SocialAuthConfiguration::query()->where('provider', 'google')->firstOrFail();
    $configuration->enabled = true;
    $configuration->web_enabled = true;
    $configuration->registration_enabled = true;
    $configuration->active_environment = 'test';
    $configuration->test_client_id = 'test-web-client-id';
    $configuration->test_client_secret = 'test-web-client-secret';
    $configuration->save();

    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page->where('googleSignInEnabled', true));
    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page->where('googleSignUpEnabled', true));
});

test('API Google login stores a short-lived token matching expires_in', function () {
    config(['bulbula-api.token.expiration' => 30]);
    configureGoogleApiAuth();

    $challenge = app(SocialAuthChallengeService::class)
        ->issueNonce(SocialAuthChallengeService::GOOGLE_LOGIN_NONCE);

    $this->mock(GoogleIdentityTokenVerifier::class, function (MockInterface $mock) use ($challenge) {
        $mock->shouldReceive('verify')
            ->once()
            ->with('signed-google-id-token', ['test-mobile-client-id'], 10)
            ->andReturn([
                'aud' => 'test-mobile-client-id',
                'iss' => 'https://accounts.google.com',
                'sub' => 'google-api-subject-789',
                'iat' => now()->timestamp,
                'exp' => now()->addMinutes(5)->timestamp,
                'nonce' => $challenge['token'],
                'email' => 'api-customer@example.com',
                'email_verified' => true,
                'name' => 'API Customer',
            ]);
    });

    $response = $this->postJson('/api/v1/auth/google/login', [
        'id_token' => 'signed-google-id-token',
        'nonce' => $challenge['token'],
        'device_name' => 'test-mobile-app',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.expires_in', 1800);

    $plainTextToken = $response->json('data.token');
    $tokenId = explode('|', $plainTextToken, 2)[0];
    $storedToken = DB::table('personal_access_tokens')->where('id', $tokenId)->first();

    expect($storedToken)->not->toBeNull()
        ->and($storedToken->expires_at)->not->toBeNull()
        ->and(\Illuminate\Support\Carbon::parse($storedToken->expires_at)->between(
            now()->addMinutes(29),
            now()->addMinutes(31),
        ))->toBeTrue();
});

test('API Google account linking requires an authenticated customer password', function () {
    configureGoogleApiAuth();

    $user = User::factory()->create([
        'password' => 'correct-password',
        'role' => 'customer',
        'is_active' => true,
    ]);
    Sanctum::actingAs($user);

    $passwordResponse = $this->postJson('/api/v1/auth/google/link/nonce', [
        'current_password' => 'wrong-password',
    ]);
    $passwordResponse->assertForbidden()
        ->assertJsonPath('error_code', 'INVALID_PASSWORD');

    expect(DB::table('social_auth_challenges')->count())->toBe(0);
});

test('admin can configure separate OAuth profiles without exposing secrets', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->actingAs($admin, 'admin');

    $this->put(route('admin.system.authentication.google.update'), [
        'enabled' => true,
        'active_environment' => 'test',
        'web_enabled' => true,
        'api_enabled' => true,
        'registration_enabled' => true,
        'max_id_token_age_minutes' => 10,
        'test_client_id' => 'test-web-client-id',
        'test_client_secret' => 'test-web-client-secret',
        'clear_test_client_secret' => false,
        'test_api_audiences_text' => "test-android-client\ntest-ios-client",
        'live_client_id' => 'live-web-client-id',
        'live_client_secret' => 'live-web-client-secret',
        'clear_live_client_secret' => false,
        'live_api_audiences_text' => "live-android-client\nlive-ios-client",
    ])->assertRedirect(route('admin.system.authentication.google.index'))
        ->assertSessionHas('success', 'Google authentication settings saved.');

    $configuration = SocialAuthConfiguration::query()->where('provider', 'google')->firstOrFail();

    expect($configuration->enabled)->toBeTrue()
        ->and($configuration->active_environment)->toBe('test')
        ->and($configuration->web_enabled)->toBeTrue()
        ->and($configuration->api_enabled)->toBeTrue()
        ->and($configuration->test_client_secret)->toBe('test-web-client-secret')
        ->and($configuration->live_client_secret)->toBe('live-web-client-secret')
        ->and($configuration->getRawOriginal('test_client_secret'))->not->toBe('test-web-client-secret')
        ->and($configuration->getRawOriginal('live_client_secret'))->not->toBe('live-web-client-secret')
        ->and($configuration->test_api_audiences)->toBe(['test-android-client', 'test-ios-client']);

    $response = $this->get(route('admin.system.authentication.google.index'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/System/Authentication/Google')
            ->where('settings.test_client_secret_configured', true)
            ->where('settings.live_client_secret_configured', true)
            ->where('settings.active_web_ready', true)
            ->where('settings.active_api_ready', true)
            ->missing('settings.test_client_secret')
            ->missing('settings.live_client_secret'));

    expect($response->getContent())->not->toContain('test-web-client-secret')
        ->and($response->getContent())->not->toContain('live-web-client-secret');

    $this->put(route('admin.system.authentication.google.update'), [
        'enabled' => true,
        'active_environment' => 'test',
        'web_enabled' => true,
        'api_enabled' => true,
        'registration_enabled' => true,
        'max_id_token_age_minutes' => 10,
        'test_client_id' => 'test-web-client-id',
        'test_client_secret' => '',
        'clear_test_client_secret' => false,
        'test_api_audiences_text' => "test-android-client\ntest-ios-client",
        'live_client_id' => 'live-web-client-id',
        'live_client_secret' => '',
        'clear_live_client_secret' => false,
        'live_api_audiences_text' => "live-android-client\nlive-ios-client",
    ])->assertRedirect(route('admin.system.authentication.google.index'))
        ->assertSessionHas('success', 'Google authentication settings saved.');

    $configuration->refresh();
    expect($configuration->test_client_secret)->toBe('test-web-client-secret')
        ->and($configuration->live_client_secret)->toBe('live-web-client-secret')
        ->and($configuration->enabled)->toBeTrue();
});

test('admin cannot enable a web profile without both OAuth credentials', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $this->actingAs($admin, 'admin');

    $this->from(route('admin.system.authentication.google.index'))
        ->put(route('admin.system.authentication.google.update'), [
            'enabled' => true,
            'active_environment' => 'test',
            'web_enabled' => true,
            'api_enabled' => false,
            'registration_enabled' => true,
            'max_id_token_age_minutes' => 10,
            'test_client_id' => '',
            'test_client_secret' => '',
            'clear_test_client_secret' => false,
            'test_api_audiences_text' => '',
            'live_client_id' => '',
            'live_client_secret' => '',
            'clear_live_client_secret' => false,
            'live_api_audiences_text' => '',
        ])
        ->assertRedirect(route('admin.system.authentication.google.index'))
        ->assertSessionHasErrors('active_environment');

    expect(SocialAuthConfiguration::query()->where('provider', 'google')->value('enabled'))->toBeFalse();
});

test('customer account cannot access admin Google authentication settings', function () {
    $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
    $this->actingAs($customer, 'admin');

    $this->get(route('admin.system.authentication.google.index'))
        ->assertRedirect(route('admin.login'));
});

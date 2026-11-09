<?php

declare(strict_types=1);

use Bulbula\Core\Services\SettingService;

test('public app settings exposes a versioned mobile-safe configuration projection', function () {
    $settings = app(SettingService::class);
    $settings->set('site_name', 'Old Site Name', 'string', 'general');
    $settings->set('store_name', 'BNEO', 'string', 'store');
    $settings->set('site_tagline', 'Shop Easly!', 'string', 'general');
    $settings->set('store_description', 'A local Ethiopian marketplace.', 'string', 'store');
    $settings->set('support_email', 'help@example.test', 'string', 'store');
    $settings->set('store_email', 'orders@example.test', 'string', 'store');
    $settings->set('store_phone', '+251 911 000 000', 'string', 'store');
    $settings->set('store_country', 'Ethiopia', 'string', 'store');
    $settings->set('store_timezone', 'Africa/Addis_Ababa', 'string', 'store');
    $settings->set('policy_privacy', 'Privacy text.', 'string', 'store');
    $settings->set('policy_terms', 'Terms text.', 'string', 'store');
    $settings->set('policy_return', 'Returns text.', 'string', 'store');
    $settings->set('policy_shipping', 'Shipping text.', 'string', 'store');
    $settings->set('admin_email', 'private-admin@example.test', 'string', 'general');
    $settings->set('razorpay_secret', 'must-not-be-public', 'string', 'payment');

    $response = $this->getJson('/api/v1/app/settings')->assertOk();
    $response
        ->assertHeader('Cache-Control', 'public, max-age=60')
        ->assertHeader('ETag', '"' . $response->json('data.mobile_config.revision') . '"')
        ->assertJsonMissingPath('data.mobile_config.api_base_url')
        ->assertJsonMissingPath('data.mobile_config.razorpay_secret');

    $response
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.site_name', 'Old Site Name')
        ->assertJsonPath('data.mobile_config.schema_version', 1)
        ->assertJsonPath('data.mobile_config.brand.name', 'BNEO')
        ->assertJsonPath('data.mobile_config.brand.tagline', 'Shop Easly!')
        ->assertJsonPath('data.mobile_config.brand.description', 'A local Ethiopian marketplace.')
        ->assertJsonPath('data.mobile_config.support.email', 'help@example.test')
        ->assertJsonPath('data.mobile_config.support.phone', '+251 911 000 000')
        ->assertJsonPath('data.mobile_config.store.country', 'Ethiopia')
        ->assertJsonPath('data.mobile_config.store.timezone', 'Africa/Addis_Ababa')
        ->assertJsonPath('data.mobile_config.policies.privacy', 'Privacy text.')
        ->assertJsonPath('data.mobile_config.policies.terms', 'Terms text.')
        ->assertJsonPath('data.mobile_config.policies.returns', 'Returns text.')
        ->assertJsonPath('data.mobile_config.policies.shipping', 'Shipping text.');

    $mobileConfig = $response->json('data.mobile_config');
    expect($mobileConfig['revision'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($mobileConfig)->not->toHaveKey('admin_email')
        ->and($mobileConfig)->not->toHaveKey('razorpay_secret')
        ->and($mobileConfig)->not->toHaveKey('api_base_url')
        ->and($mobileConfig)->not->toHaveKey('token');
});

test('public app settings falls back to the general site name and site logo when mobile values are absent', function () {
    $settings = app(SettingService::class);
    $settings->set('site_name', 'BNEO', 'string', 'general');
    $settings->set('store_name', '', 'string', 'store');
    $settings->set('site_logo', 'settings/site-logo.png', 'string', 'general');
    $settings->set('mobile_auth_logo', '', 'string', 'general');

    $response = $this->getJson('/api/v1/app/settings')->assertOk();
    $config = $response->json('data.mobile_config');

    expect($config['brand']['name'])->toBe('BNEO')
        ->and($config['brand']['auth_logo_url'])->toBe($config['brand']['logo_url'])
        ->and($config['revision'])->toBeString();
});

<?php

declare(strict_types=1);

use App\Models\User;
use Bulbula\Core\Services\SettingService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->withoutTwoFactor()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
});

test('mobile branding colors default on the Store Configuration screen', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.settings.store.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.mobile_primary_color', '#1268C4')
            ->where('settings.mobile_accent_color', '#0C62B8')
        );
});

test('saving the Mobile App section stores only valid RGB colors', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.settings.store.save'), [
            '_section' => 'mobile',
            'mobile_primary_color' => '#1A2B3C',
            'mobile_accent_color' => '#095AB1',
            'store_name' => 'must not be saved by the Mobile App section',
        ])
        ->assertRedirect(route('admin.settings.store.index'))
        ->assertSessionHas('success');

    $settings = app(SettingService::class);
    expect($settings->get('mobile_primary_color'))->toBe('#1A2B3C')
        ->and($settings->get('mobile_accent_color'))->toBe('#095AB1')
        ->and($settings->get('store_name'))->not->toBe('must not be saved by the Mobile App section');
});

test('Mobile App section rejects colors that do not meet the white-text contrast threshold', function () {
    $this->actingAs($this->admin, 'admin')
        ->from(route('admin.settings.store.index'))
        ->post(route('admin.settings.store.save'), [
            '_section' => 'mobile',
            'mobile_primary_color' => '#FFFFFF',
            'mobile_accent_color' => '#AABBCC',
        ])
        ->assertRedirect(route('admin.settings.store.index'))
        ->assertSessionHasErrors(['mobile_primary_color', 'mobile_accent_color']);
});

test('Mobile App section rejects colors with unsupported syntax', function () {
    $this->actingAs($this->admin, 'admin')
        ->from(route('admin.settings.store.index'))
        ->post(route('admin.settings.store.save'), [
            '_section' => 'mobile',
            'mobile_primary_color' => 'red; background:url(https://example.test)',
            'mobile_accent_color' => '#12AB',
        ])
        ->assertRedirect(route('admin.settings.store.index'))
        ->assertSessionHasErrors(['mobile_primary_color', 'mobile_accent_color']);
});

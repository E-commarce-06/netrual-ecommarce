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

test('both admin screens display the same name when legacy settings differ', function () {
    $settings = app(SettingService::class);
    $settings->set('site_name', 'BNEO', 'string', 'general');
    $settings->set('store_name', 'Arkeonet Commerce', 'string', 'store');

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.settings.general.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.site_name', 'Arkeonet Commerce')
        );

    $this->get(route('admin.settings.store.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.store_name', 'Arkeonet Commerce')
        );
});

test('saving General Settings keeps the Store Configuration name in sync', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.settings.general.save'), [
            'site_name' => 'Arkeonet Commerce',
            'site_tagline' => 'A local online store',
            'admin_email' => 'admin@example.com',
            'store_country' => 'Ethiopia',
        ])
        ->assertRedirect();

    $settings = app(SettingService::class);

    expect($settings->get('site_name'))->toBe('Arkeonet Commerce')
        ->and($settings->get('store_name'))->toBe('Arkeonet Commerce');
});

test('saving Store Configuration keeps the General Settings name in sync', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.settings.store.save'), [
            'store_name' => 'Arkeonet Commerce',
            'store_email' => 'support@example.com',
            'store_phone' => '+251 911 000 000',
            'store_address_1' => 'Bole Road',
            'store_city' => 'Addis Ababa',
            'store_state' => 'Addis Ababa',
            'store_postal_code' => '1000',
            'store_country' => 'Ethiopia',
            'store_timezone' => 'Africa/Addis_Ababa',
        ])
        ->assertRedirect(route('admin.settings.store.index'));

    $settings = app(SettingService::class);

    expect($settings->get('store_name'))->toBe('Arkeonet Commerce')
        ->and($settings->get('site_name'))->toBe('Arkeonet Commerce');
});

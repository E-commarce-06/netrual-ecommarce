<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Bulbula\Core\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->withoutTwoFactor()->create([
        'role'      => 'admin',
        'is_active' => true,
    ]);

    $this->theme = Theme::create([
        'name'         => 'Bulbula Default',
        'slug'         => 'bulbula-default',
        'catalog_slug' => 'bulbula-default',
        'version'      => '1.0.0',
        'category'     => 'general',
        'is_active'    => true,
        'is_default'   => true,
        'settings'     => null,
    ]);
});

test('admin can open theme settings with the theme schema', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.settings.theme.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Settings/Theme/Index')
        ->where('theme.slug', 'bulbula-default')
        ->has('sections')
        ->where('values.colors.primary_color', '#2563eb')
    );
});

test('guests cannot open theme settings', function () {
    $this->get(route('admin.settings.theme.index'))
        ->assertRedirect(route('admin.login'));
});

test('admin can save theme colors and feature toggles', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.settings.theme.save'), [
            'settings' => [
                'colors'   => ['primary_color' => '#123456'],
                'features' => ['sticky_header' => false],
            ],
        ])
        ->assertRedirect();

    $saved = $this->theme->fresh()->settings;

    expect($saved['colors']['primary_color'])->toBe('#123456')
        ->and($saved['features']['sticky_header'])->toBeFalse()
        // Unsubmitted fields are persisted with their schema defaults so
        // the stored settings are always complete.
        ->and($saved['colors']['secondary_color'])->toBe('#1e40af')
        ->and($saved['typography']['font_family'])->toBe('Inter, sans-serif');
});

test('invalid color values fall back to schema defaults', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.settings.theme.save'), [
            'settings' => [
                'colors' => ['primary_color' => 'not-a-color'],
            ],
        ])
        ->assertRedirect();

    expect($this->theme->fresh()->settings['colors']['primary_color'])
        ->toBe('#2563eb');
});

<?php

use Illuminate\Support\Facades\DB;

test('setup complete seeds admin menus and core store data on an empty install', function () {
    expect(DB::table('menu_items')->count())->toBe(0)
        ->and(DB::table('countries')->count())->toBe(0);

    $this->postJson('/setup/complete')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(DB::table('menu_items')->count())->toBeGreaterThan(0)
        ->and(DB::table('countries')->count())->toBeGreaterThan(0)
        ->and(DB::table('tax_rates')->count())->toBeGreaterThan(0)
        ->and(DB::table('shipping_methods')->count())->toBeGreaterThan(0)
        ->and(DB::table('email_templates')->count())->toBeGreaterThan(0)
        ->and(DB::table('customer_groups')->count())->toBeGreaterThan(0)
        ->and(DB::table('settings')->where('key', 'setup_completed')->value('value'))->toBe('1');
});

test('setup complete does not duplicate rows when core data already exists', function () {
    $this->postJson('/setup/complete')->assertOk();

    $countries = DB::table('countries')->count();
    $menus = DB::table('menu_items')->count();

    // Re-open the wizard (as if setup had not been marked complete) and finish again.
    DB::table('settings')->where('key', 'setup_completed')->update(['value' => '0']);
    $this->postJson('/setup/complete')->assertOk();

    expect(DB::table('countries')->count())->toBe($countries)
        ->and(DB::table('menu_items')->count())->toBe($menus);
});

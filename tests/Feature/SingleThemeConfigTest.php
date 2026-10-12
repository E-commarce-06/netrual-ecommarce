<?php

use Bulbula\Core\Models\Theme;
use Bulbula\Shop\Database\Seeders\ThemeSeeder;
use Illuminate\Support\Facades\DB;

// Single-theme setup: the active theme comes from config, and the admin has no theme screens.

it('active theme comes from config, not the is_active flag', function () {
    $this->seed(ThemeSeeder::class);

    expect(Theme::active()?->slug)->toBe(config('theme.default'));

    // Flag pointing at another row must not change the active theme.
    Theme::query()->update(['is_active' => false]);
    Theme::create(['slug' => 'other-theme', 'name' => 'Other', 'version' => '1.0.0', 'is_active' => true, 'category' => 'general', 'source' => 'catalog']);
    expect(Theme::active()?->slug)->toBe(config('theme.default'));

    config(['theme.default' => 'nonexistent-theme']);
    expect(Theme::active())->toBeNull();
});

it('admin theme routes are gone', function () {
    $names = collect(app('router')->getRoutes()->getRoutes())->map->getName()->filter();
    expect($names->filter(fn ($n) => str_contains($n, 'admin.themes') || str_contains($n, 'admin.appearance'))->values()->all())->toBe([]);
});

it('admin menu has no appearance entries after seeding, and migration removes legacy rows', function () {
    $this->seed(\Bulbula\Admin\Database\Seeders\AdminMenuSeeder::class);
    expect(DB::table('menu_items')->where('key', 'like', 'appearance%')->count())->toBe(0);

    // Simulate a legacy DB row, then run the cleanup migration.
    DB::table('menu_items')->insert(['key' => 'appearance', 'title' => 'Appearance', 'location' => 'admin', 'order' => 75, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $migration = require base_path('packages/Bulbula/Admin/src/Database/Migrations/2026_10_12_000001_remove_appearance_menu_items.php');
    $migration->up();
    expect(DB::table('menu_items')->where('key', 'like', 'appearance%')->count())->toBe(0);
});

it('storefront home renders with the single theme', function () {
    $this->seed(ThemeSeeder::class);
    $this->get('/')->assertStatus(200);
});

it('admin appearance URLs are not routed', function () {
    // Guest: a missing route is 404 before auth; a live admin route would redirect to login.
    $this->get('/admin/appearance')->assertNotFound();
    $this->get('/admin/appearance/themes')->assertNotFound();
    $this->get('/admin/appearance/themes/bulbula-default/settings')->assertNotFound();
});

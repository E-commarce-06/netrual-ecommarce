<?php

namespace Bulbula\Shop\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Bulbula\Core\Models\Theme;
use Bulbula\CMS\Services\StorefrontMenuSyncService;
use Bulbula\UIEditor\Models\PageLayout;
use Bulbula\UIEditor\Database\Seeders\UIEditorPageSeeder;

class ThemeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $themePath = base_path('templates/storefront/general/bulbula-default');

        // ── 1. Theme record ────────────────────────────────────────────────
        $themeConfig = json_decode(file_get_contents($themePath . '/theme.json'), true);

        Theme::updateOrCreate(
            ['slug' => 'bulbula-default'],
            [
                'name'        => $themeConfig['name'],
                'description' => $themeConfig['description'],
                'version'     => $themeConfig['version'],
                'author'      => $themeConfig['author'],
                'screenshot'  => $themeConfig['screenshot'] ?? null,
                'is_active'   => true,
                'is_default'  => true,
                'catalog_slug' => 'bulbula-default',
                'source'      => 'bundled',
                'category'    => 'general',
                'settings'    => $themeConfig['settings'] ?? [],
            ]
        );

        $this->command->info(('✓ ' . config('brand.name') . ' Default theme seeded successfully!'));

        // Publish theme public assets (hero images, etc.)
        Artisan::call('theme:discover');
        $this->command->info('  ↳ Theme assets published to public/templates/bulbula-default/.');

        // ── 2. Homepage UIEditor layout ────────────────────────────────────
        // Only seed if NO published homepage layout exists yet.
        // This allows admins to customise the homepage without it being overwritten on re-seed.
        $existingPublished = PageLayout::homepage()->published()->first();

        if ($existingPublished) {
            $this->command->info('  ↳ Homepage layout already published — skipping homepage seed.');
        } else {
            $themeDataPath = $themePath . '/data/theme-data.json';

            if (! file_exists($themeDataPath)) {
                $this->command->warn('  ↳ theme-data.json not found — homepage layout not seeded.');
            } else {
                $themeData    = json_decode(file_get_contents($themeDataPath), true);
                $homepageData = $themeData['homepage'] ?? null;

                if (! $homepageData) {
                    $this->command->warn('  ↳ No [homepage] key in theme-data.json — skipping homepage seed.');
                } else {
                    // Delete any existing draft homepage layout before creating the seeded one
                    PageLayout::homepage()->delete();

                    PageLayout::create([
                        'page_type'    => PageLayout::TYPE_HOMEPAGE,
                        'page_id'      => null,
                        'layout_data'  => $homepageData,
                        'status'       => PageLayout::STATUS_PUBLISHED,
                        'published_at' => now(),
                    ]);

                    $this->command->info('  ↳ Homepage layout seeded and published (' . count($homepageData['sections'] ?? []) . ' sections).');
                }
            }
        }

        // ── 3. CMS page UIEditor layouts (always — independent of homepage) ─
        $this->call(UIEditorPageSeeder::class);

        // ── 4. Sync storefront category menu from catalog (if categories exist) ─
        $sync = app(StorefrontMenuSyncService::class);
        $sync->fixDealsUrls();
        $count = $sync->syncCategoryMenuItems();

        if ($count > 0) {
            $this->command->info("  ↳ Synced {$count} category menu item(s) from catalog.");
        }
    }
}

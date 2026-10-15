<?php

namespace Bulbula\Shop\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Bulbula\Core\Models\Theme;
use Bulbula\CMS\Models\Page;
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
        // The single storefront theme, chosen by config (BRAND_DEFAULT_THEME).
        $slug = (string) config('theme.default');
        $themePath = base_path('templates/storefront/general/'.$slug);

        // ── 1. Theme record ────────────────────────────────────────────────
        $themeConfig = json_decode(file_get_contents($themePath . '/theme.json'), true);

        Theme::updateOrCreate(
            ['slug' => $slug],
            [
                'name'        => $themeConfig['name'],
                'description' => $themeConfig['description'],
                'version'     => $themeConfig['version'],
                'author'      => $themeConfig['author'],
                'screenshot'  => $themeConfig['screenshot'] ?? null,
                'is_active'   => true,
                'is_default'  => true,
                'catalog_slug' => $slug,
                'source'      => 'bundled',
                'category'    => 'general',
                'settings'    => $themeConfig['settings'] ?? [],
            ]
        );

        $this->command->info(('✓ ' . config('brand.name') . ' Default theme seeded successfully!'));

        // Publish theme public assets (hero images, etc.)
        Artisan::call('theme:discover');
        $this->command->info('  ↳ Theme assets published to public/templates/'.$slug.'/.');

        // ── 1b. Homepage CMS page (block-editor entry point) ─────────────
        // The editor opens the homepage through a CMS page flagged
        // is_homepage = true. Seed it alongside the layout so installs made
        // through the web setup wizard (which seeds pages separately) still
        // get an editable homepage.
        if (! Page::homepage()->exists()) {
            Page::firstOrCreate(
                ['url_key' => 'home'],
                [
                    'title'       => 'Homepage',
                    'content'     => '',
                    'meta_title'  => 'Home',
                    'status'      => 'published',
                    'is_homepage' => true,
                ]
            );

            // firstOrCreate leaves an existing unflagged "home" page as-is.
            Page::where('url_key', 'home')
                ->where('is_homepage', false)
                ->update(['is_homepage' => true]);

            $this->command->info('  ↳ Homepage CMS page seeded (block-editor entry point).');
        }

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

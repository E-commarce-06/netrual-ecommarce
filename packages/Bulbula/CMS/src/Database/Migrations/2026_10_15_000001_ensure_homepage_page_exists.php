<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure the CMS "Homepage" page record exists.
     *
     * The homepage layout (hero slider, promo banner, sections) is stored in
     * uieditor_page_layouts and seeded from the theme, but the block editor
     * is opened through a CMS page flagged is_homepage = true. Installs
     * created through the web setup wizard received the layout without that
     * page record, leaving the homepage impossible to edit from the admin
     * panel. This backfills the missing entry point without touching any
     * existing page or layout.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $homepageExists = DB::table('pages')
            ->where('is_homepage', true)
            ->whereNull('deleted_at')
            ->exists();

        if ($homepageExists) {
            return;
        }

        // A "home" page may exist without the homepage flag — flag it
        // instead of creating a duplicate (url_key is unique).
        $existing = DB::table('pages')->where('url_key', 'home')->first();

        if ($existing) {
            DB::table('pages')->where('id', $existing->id)->update([
                'is_homepage' => true,
                'updated_at'  => now(),
            ]);

            return;
        }

        DB::table('pages')->insert([
            'title'            => 'Homepage',
            'url_key'          => 'home',
            'content'          => '',
            'meta_title'       => 'Home',
            'meta_description' => null,
            'meta_keywords'    => null,
            'status'           => 'published',
            'is_homepage'      => true,
            'created_by'       => null,
            'updated_by'       => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        DB::table('pages')
            ->where('url_key', 'home')
            ->where('is_homepage', true)
            ->delete();
    }
};

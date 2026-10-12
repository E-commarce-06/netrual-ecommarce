<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single-theme setup has no admin theme screens. Remove the Appearance menu
 * entries (Design Templates, Theme Setting) that pointed at removed routes.
 * Children are deleted before their parent.
 */
return new class extends Migration
{
    private const KEYS = ['appearance-theme-setting', 'appearance-theme', 'appearance'];

    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        foreach (self::KEYS as $key) {
            DB::table('menu_items')->where('key', $key)->delete();
        }
    }

    public function down(): void
    {
        // Removed screens are not restored. The seeder no longer defines these items.
    }
};

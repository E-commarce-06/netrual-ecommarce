<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        $systemId = DB::table('menu_items')
            ->where('key', 'system')
            ->where('location', 'admin')
            ->value('id');

        // Existing installations normally have the System parent. Fresh installs
        // receive this entry from AdminMenuSeeder after migrations are complete.
        if (! $systemId) {
            return;
        }

        DB::table('menu_items')->updateOrInsert(
            ['key' => 'system-authentication'],
            [
                'title' => 'Authentication',
                'icon' => 'shield-check',
                'route' => 'admin.system.authentication.google.index',
                'url' => null,
                'parent_id' => $systemId,
                'order' => 10,
                'permission' => null,
                'location' => 'admin',
                'active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_items')) {
            DB::table('menu_items')->where('key', 'system-authentication')->delete();
        }
    }
};

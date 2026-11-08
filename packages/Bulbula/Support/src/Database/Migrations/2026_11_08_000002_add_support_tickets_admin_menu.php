<?php

declare(strict_types=1);

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

        $salesId = DB::table('menu_items')
            ->where('key', 'sales')
            ->where('location', 'admin')
            ->value('id');

        // Fresh installations receive the item from AdminMenuSeeder after migration.
        if (! $salesId) {
            return;
        }

        DB::table('menu_items')->updateOrInsert(
            ['key' => 'sales-support'],
            [
                'title' => 'Support tickets',
                'icon' => 'message-circle',
                'route' => 'admin.support.index',
                'url' => null,
                'parent_id' => $salesId,
                'order' => 2,
                'permission' => null,
                'location' => 'admin',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_items')) {
            DB::table('menu_items')->where('key', 'sales-support')->delete();
        }
    }
};

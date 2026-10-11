<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Bulbula\Core\Database\Seeders\DatabaseSeeder as BulbulaDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the main Bulbula Core Seeder which orchestrates all package seeders
        $this->call(BulbulaDatabaseSeeder::class);
        
        // You can add project-specific seeders here if needed
        // $this->call(TransactionSeeder::class);
    }
}

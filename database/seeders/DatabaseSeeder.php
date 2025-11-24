<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PackagesSeeder::class,
            BouquetsSeeder::class,
            PackageBouquetSeeder::class,
            PM2WorkersSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}

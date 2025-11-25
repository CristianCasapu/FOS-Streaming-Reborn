<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Database Seeder
 *
 * Main seeder that orchestrates all other seeders
 * Run with: php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order is important - dependencies must be seeded first:
     * 1. Core settings and configuration
     * 2. Admin/Staff accounts
     * 3. Packages (before bouquets)
     * 4. Bouquets (before package_bouquet)
     * 5. Package-Bouquet relationships
     * 6. PM2 Workers
     * 7. Transcode profiles
     * 8. Optional: Admin roles, Resellers, V2Ray (for dev environments)
     */
    public function run(): void
    {
        // Core system configuration
        $this->call([
            SettingsSeeder::class,
            AdminSeeder::class,
        ]);

        // Subscription system
        $this->call([
            PackagesSeeder::class,
            BouquetsSeeder::class,
            PackageBouquetSeeder::class,
        ]);

        // Background workers
        $this->call([
            PM2WorkersSeeder::class,
        ]);

        // Streaming configuration
        $this->call([
            TranscodeProfilesSeeder::class,
        ]);

        // Optional seeders (skipped in production for some)
        $this->call([
            AdminRolesSeeder::class,
            ResellersSeeder::class,
            V2RayServersSeeder::class,
        ]);
    }
}

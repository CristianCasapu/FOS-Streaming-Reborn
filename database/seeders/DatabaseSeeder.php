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
     * 3. Categories (before streams/bouquets)
     * 4. Packages (before bouquets)
     * 5. Bouquets (before package_bouquet)
     * 6. Package-Bouquet relationships
     * 7. PM2 Workers
     * 8. Transcode profiles
     * 9. Security: UFW Rules
     * 10. Optional: Admin roles, Resellers, V2Ray (for dev environments)
     */
    public function run(): void
    {
        // Core system configuration
        $this->call([
            SettingsSeeder::class,
            AdminSeeder::class,
        ]);

        // Content organization
        $this->call([
            CategoriesSeeder::class,
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

        // Security configuration
        $this->call([
            UfwRulesSeeder::class,
        ]);

        // Optional seeders (skipped in production for some)
        $this->call([
            AdminRolesSeeder::class,
            ResellersSeeder::class,
            V2RayServersSeeder::class,
        ]);
    }
}

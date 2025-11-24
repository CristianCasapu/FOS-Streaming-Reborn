<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

class PackagesSeeder extends Seeder
{
    public $command;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $packages = [
            [
                'name' => 'Basic',
                'description' => 'Basic package with essential channels - perfect for casual viewers',
                'max_concurrent_devices' => 1,
                'bandwidth_limit_mbps' => 5,
                'video_quality' => 'SD',
                'allow_recording' => 0,
                'allow_timeshifting' => 0,
                'price' => 9.99,
                'duration_days' => 30,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Standard',
                'description' => 'Standard package with popular channels and HD quality',
                'max_concurrent_devices' => 2,
                'bandwidth_limit_mbps' => 10,
                'video_quality' => 'HD',
                'allow_recording' => 0,
                'allow_timeshifting' => 1,
                'price' => 19.99,
                'duration_days' => 30,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Premium',
                'description' => 'Premium package with all channels, Full HD quality, and recording',
                'max_concurrent_devices' => 5,
                'bandwidth_limit_mbps' => 25,
                'video_quality' => 'FHD',
                'allow_recording' => 1,
                'allow_timeshifting' => 1,
                'price' => 29.99,
                'duration_days' => 30,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Enterprise package with unlimited devices, 4K quality, and all features',
                'max_concurrent_devices' => 10,
                'bandwidth_limit_mbps' => null, // Unlimited
                'video_quality' => 'UHD',
                'allow_recording' => 1,
                'allow_timeshifting' => 1,
                'price' => 99.99,
                'duration_days' => 30,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        foreach ($packages as $package) {
            // Check if package exists by name
            $exists = Capsule::table('packages')->where('name', $package['name'])->exists();
            if (!$exists) {
                Capsule::table('packages')->insert($package);
            }
        }

        if ($this->command) {
            $this->command->info('Packages seeded successfully!');
        }
    }
}

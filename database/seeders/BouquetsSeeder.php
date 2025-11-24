<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

class BouquetsSeeder extends Seeder
{
    public $command;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $bouquets = [
            [
                'name' => 'Sports',
                'description' => 'All major sports channels - Football, Basketball, Tennis, etc.',
                'stream_ids' => json_encode([]), // Empty array initially - populate with stream IDs
                'is_active' => 1,
                'sort_order' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Movies',
                'description' => 'Premium movie channels with latest releases and classics',
                'stream_ids' => json_encode([]),
                'is_active' => 1,
                'sort_order' => 2,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'News',
                'description' => 'International and local news channels - Stay informed 24/7',
                'stream_ids' => json_encode([]),
                'is_active' => 1,
                'sort_order' => 3,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Entertainment',
                'description' => 'General entertainment, series, reality shows, and more',
                'stream_ids' => json_encode([]),
                'is_active' => 1,
                'sort_order' => 4,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Kids',
                'description' => 'Family-friendly content for children of all ages',
                'stream_ids' => json_encode([]),
                'is_active' => 1,
                'sort_order' => 5,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Documentary',
                'description' => 'Educational documentaries, nature, science, and history',
                'stream_ids' => json_encode([]),
                'is_active' => 1,
                'sort_order' => 6,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Music',
                'description' => 'Music channels, concerts, and music videos',
                'stream_ids' => json_encode([]),
                'is_active' => 1,
                'sort_order' => 7,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Premium',
                'description' => 'Exclusive premium channels with special content',
                'stream_ids' => json_encode([]),
                'is_active' => 1,
                'sort_order' => 8,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        foreach ($bouquets as $bouquet) {
            // Check if bouquet exists by name
            $exists = Capsule::table('bouquets')->where('name', $bouquet['name'])->exists();
            if (!$exists) {
                Capsule::table('bouquets')->insert($bouquet);
            }
        }

        if ($this->command) {
            $this->command->info('Bouquets seeded successfully!');
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Categories Seeder
 *
 * Seeds default stream categories for organizing channels.
 * Categories are used to group streams (Sports, Movies, News, etc.)
 */
class CategoriesSeeder extends Seeder
{
    public $command;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Seeding Categories...');
        }

        $timestamp = date('Y-m-d H:i:s');

        $categories = [
            [
                'name' => 'General',
                'description' => 'General streaming content',
                'icon' => 'tv',
                'color' => '#6B7280',
                'sort_order' => 1,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Sports',
                'description' => 'Live sports events, matches, and sports news',
                'icon' => 'trophy',
                'color' => '#22C55E',
                'sort_order' => 2,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Movies',
                'description' => 'Movie channels, premieres, and film content',
                'icon' => 'film',
                'color' => '#EF4444',
                'sort_order' => 3,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'News',
                'description' => 'News channels, breaking news, and current affairs',
                'icon' => 'newspaper',
                'color' => '#3B82F6',
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Entertainment',
                'description' => 'Entertainment channels, series, and reality shows',
                'icon' => 'star',
                'color' => '#A855F7',
                'sort_order' => 5,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Documentary',
                'description' => 'Documentary channels, nature, science, and history',
                'icon' => 'book-open',
                'color' => '#F59E0B',
                'sort_order' => 6,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Kids',
                'description' => 'Children programming and family-friendly content',
                'icon' => 'smile',
                'color' => '#EC4899',
                'sort_order' => 7,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Music',
                'description' => 'Music channels, concerts, and music videos',
                'icon' => 'music',
                'color' => '#8B5CF6',
                'sort_order' => 8,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'International',
                'description' => 'International channels from various countries',
                'icon' => 'globe',
                'color' => '#14B8A6',
                'sort_order' => 9,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'name' => 'Premium',
                'description' => 'Premium exclusive content and channels',
                'icon' => 'crown',
                'color' => '#F97316',
                'sort_order' => 10,
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];

        $created = 0;
        foreach ($categories as $categoryData) {
            // Check if category already exists by name
            $exists = Capsule::table('categories')->where('name', $categoryData['name'])->exists();

            if ($exists) {
                if ($this->command) {
                    $this->command->warn("  Category '{$categoryData['name']}' already exists");
                }
                continue;
            }

            Capsule::table('categories')->insert($categoryData);
            $created++;

            if ($this->command) {
                $this->command->info("  Created category: {$categoryData['name']}");
            }
        }

        if ($this->command) {
            $this->command->info('');
            $this->command->info("Categories seeded successfully! ({$created} created)");
        }
    }
}

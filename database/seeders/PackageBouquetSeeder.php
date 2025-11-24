<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

class PackageBouquetSeeder extends Seeder
{
    public $command;

    /**
     * Run the database seeds.
     *
     * Link packages to bouquets (many-to-many relationship)
     */
    public function run(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        // Get package and bouquet IDs
        $basicId = Capsule::table('packages')->where('name', 'Basic')->value('id');
        $standardId = Capsule::table('packages')->where('name', 'Standard')->value('id');
        $premiumId = Capsule::table('packages')->where('name', 'Premium')->value('id');
        $enterpriseId = Capsule::table('packages')->where('name', 'Enterprise')->value('id');

        $newsId = Capsule::table('bouquets')->where('name', 'News')->value('id');
        $entertainmentId = Capsule::table('bouquets')->where('name', 'Entertainment')->value('id');
        $sportsId = Capsule::table('bouquets')->where('name', 'Sports')->value('id');
        $moviesId = Capsule::table('bouquets')->where('name', 'Movies')->value('id');
        $kidsId = Capsule::table('bouquets')->where('name', 'Kids')->value('id');
        $documentaryId = Capsule::table('bouquets')->where('name', 'Documentary')->value('id');
        $musicId = Capsule::table('bouquets')->where('name', 'Music')->value('id');
        $premiumBouquetId = Capsule::table('bouquets')->where('name', 'Premium')->value('id');

        // Basic package: News + Entertainment only
        if ($basicId && $newsId) {
            $exists = Capsule::table('package_bouquet')
                ->where('package_id', $basicId)
                ->where('bouquet_id', $newsId)
                ->exists();
            if (!$exists) {
                Capsule::table('package_bouquet')->insert([
                    'package_id' => $basicId,
                    'bouquet_id' => $newsId,
                    'created_at' => $timestamp,
                ]);
            }
        }
        if ($basicId && $entertainmentId) {
            $exists = Capsule::table('package_bouquet')
                ->where('package_id', $basicId)
                ->where('bouquet_id', $entertainmentId)
                ->exists();
            if (!$exists) {
                Capsule::table('package_bouquet')->insert([
                    'package_id' => $basicId,
                    'bouquet_id' => $entertainmentId,
                    'created_at' => $timestamp,
                ]);
            }
        }

        // Standard package: News, Entertainment, Sports, Movies, Kids
        $standardBouquets = [$newsId, $entertainmentId, $sportsId, $moviesId, $kidsId];
        foreach ($standardBouquets as $bouquetId) {
            if ($standardId && $bouquetId) {
                $exists = Capsule::table('package_bouquet')
                    ->where('package_id', $standardId)
                    ->where('bouquet_id', $bouquetId)
                    ->exists();
                if (!$exists) {
                    Capsule::table('package_bouquet')->insert([
                        'package_id' => $standardId,
                        'bouquet_id' => $bouquetId,
                        'created_at' => $timestamp,
                    ]);
                }
            }
        }

        // Premium package: All bouquets
        $allBouquets = [
            $newsId, $entertainmentId, $sportsId, $moviesId,
            $kidsId, $documentaryId, $musicId, $premiumBouquetId
        ];
        foreach ($allBouquets as $bouquetId) {
            if ($premiumId && $bouquetId) {
                $exists = Capsule::table('package_bouquet')
                    ->where('package_id', $premiumId)
                    ->where('bouquet_id', $bouquetId)
                    ->exists();
                if (!$exists) {
                    Capsule::table('package_bouquet')->insert([
                        'package_id' => $premiumId,
                        'bouquet_id' => $bouquetId,
                        'created_at' => $timestamp,
                    ]);
                }
            }
        }

        // Enterprise package: All bouquets
        foreach ($allBouquets as $bouquetId) {
            if ($enterpriseId && $bouquetId) {
                $exists = Capsule::table('package_bouquet')
                    ->where('package_id', $enterpriseId)
                    ->where('bouquet_id', $bouquetId)
                    ->exists();
                if (!$exists) {
                    Capsule::table('package_bouquet')->insert([
                        'package_id' => $enterpriseId,
                        'bouquet_id' => $bouquetId,
                        'created_at' => $timestamp,
                    ]);
                }
            }
        }

        if ($this->command) {
            $this->command->info('Package-Bouquet relationships seeded successfully!');
        }
    }
}

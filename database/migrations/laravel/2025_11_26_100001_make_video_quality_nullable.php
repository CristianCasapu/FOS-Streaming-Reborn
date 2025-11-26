<?php
/**
 * Migration: Make video_quality column nullable
 *
 * Allows NULL to represent "Any quality" (no restriction)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the column to be nullable
        Capsule::connection()->statement("
            ALTER TABLE packages
            MODIFY COLUMN video_quality ENUM('SD', 'HD', 'FHD', 'UHD') NULL DEFAULT NULL
            COMMENT 'Maximum video quality allowed, NULL means any quality'
        ");
    }

    public function down(): void
    {
        // Update any NULL values to HD before making NOT NULL
        Capsule::table('packages')
            ->whereNull('video_quality')
            ->update(['video_quality' => 'HD']);

        // Revert to NOT NULL with default
        Capsule::connection()->statement("
            ALTER TABLE packages
            MODIFY COLUMN video_quality ENUM('SD', 'HD', 'FHD', 'UHD') NOT NULL DEFAULT 'HD'
            COMMENT 'Maximum video quality allowed'
        ");
    }
};

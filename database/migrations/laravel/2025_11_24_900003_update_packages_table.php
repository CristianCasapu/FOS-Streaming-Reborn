<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update packages table to clarify that max_connections means max concurrent devices
     */
    public function up(): void
    {
        Capsule::schema()->table('packages', function (Blueprint $table) {
            // Rename max_connections to max_concurrent_devices for clarity
            if (Capsule::schema()->hasColumn('packages', 'max_connections') && !Capsule::schema()->hasColumn('packages', 'max_concurrent_devices')) {
                $table->renameColumn('max_connections', 'max_concurrent_devices');
            }

            // If neither exists, create the column
            if (!Capsule::schema()->hasColumn('packages', 'max_connections') && !Capsule::schema()->hasColumn('packages', 'max_concurrent_devices')) {
                $table->integer('max_concurrent_devices')->default(1)->after('description')
                    ->comment('Maximum concurrent devices/connections allowed');
            }

            // Add quality of service fields
            if (!Capsule::schema()->hasColumn('packages', 'bandwidth_limit_mbps')) {
                $table->integer('bandwidth_limit_mbps')->nullable()->after('max_concurrent_devices')
                    ->comment('Bandwidth limit in Mbps (null = unlimited)');
            }

            if (!Capsule::schema()->hasColumn('packages', 'video_quality')) {
                $table->enum('video_quality', ['SD', 'HD', 'FHD', 'UHD'])->default('HD')->after('bandwidth_limit_mbps')
                    ->comment('Maximum video quality allowed');
            }

            if (!Capsule::schema()->hasColumn('packages', 'allow_recording')) {
                $table->boolean('allow_recording')->default(false)->after('video_quality')
                    ->comment('Allow stream recording/download');
            }

            if (!Capsule::schema()->hasColumn('packages', 'allow_timeshifting')) {
                $table->boolean('allow_timeshifting')->default(false)->after('allow_recording')
                    ->comment('Allow timeshift/pause live TV');
            }

            // Add feature flags
            if (!Capsule::schema()->hasColumn('packages', 'features')) {
                $table->json('features')->nullable()->after('allow_timeshifting')
                    ->comment('Additional package features as JSON');
            }
        });

        // Update existing packages to use the new naming
        $this->updateExistingPackages();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Capsule::schema()->table('packages', function (Blueprint $table) {
            // Rename back
            if (Capsule::schema()->hasColumn('packages', 'max_concurrent_devices')) {
                $table->renameColumn('max_concurrent_devices', 'max_connections');
            }

            // Drop added columns
            if (Capsule::schema()->hasColumn('packages', 'bandwidth_limit_mbps')) {
                $table->dropColumn('bandwidth_limit_mbps');
            }
            if (Capsule::schema()->hasColumn('packages', 'video_quality')) {
                $table->dropColumn('video_quality');
            }
            if (Capsule::schema()->hasColumn('packages', 'allow_recording')) {
                $table->dropColumn('allow_recording');
            }
            if (Capsule::schema()->hasColumn('packages', 'allow_timeshifting')) {
                $table->dropColumn('allow_timeshifting');
            }
            if (Capsule::schema()->hasColumn('packages', 'features')) {
                $table->dropColumn('features');
            }
        });
    }

    /**
     * Update existing packages with default values
     */
    private function updateExistingPackages(): void
    {
        // Set default bandwidth and quality based on package names
        Capsule::table('packages')
            ->where('name', 'Basic')
            ->update([
                'bandwidth_limit_mbps' => 5,
                'video_quality' => 'SD',
                'allow_recording' => false,
                'allow_timeshifting' => false
            ]);

        Capsule::table('packages')
            ->where('name', 'Standard')
            ->update([
                'bandwidth_limit_mbps' => 10,
                'video_quality' => 'HD',
                'allow_recording' => false,
                'allow_timeshifting' => true
            ]);

        Capsule::table('packages')
            ->where('name', 'Premium')
            ->update([
                'bandwidth_limit_mbps' => 25,
                'video_quality' => 'FHD',
                'allow_recording' => true,
                'allow_timeshifting' => true
            ]);

        Capsule::table('packages')
            ->where('name', 'Enterprise')
            ->update([
                'bandwidth_limit_mbps' => null, // Unlimited
                'video_quality' => 'UHD',
                'allow_recording' => true,
                'allow_timeshifting' => true
            ]);
    }
};

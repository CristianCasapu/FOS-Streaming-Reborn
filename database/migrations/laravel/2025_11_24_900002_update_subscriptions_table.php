<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update subscriptions table with all necessary fields for subscription management
     * Add fields: isp, user_agent, max_concurrent_connections, current_connections
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        $schema->table('subscriptions', function (Blueprint $table) use ($schema) {
            // Max concurrent connections (from package, can be overridden per subscription)
            if (!$schema->hasColumn('subscriptions', 'max_concurrent_connections')) {
                $table->integer('max_concurrent_connections')->default(1)->after('connection_count')
                    ->comment('Maximum simultaneous connections allowed for this subscription');
            }

            // Current concurrent connections tracking
            if (!$schema->hasColumn('subscriptions', 'current_connections')) {
                $table->integer('current_connections')->default(0)->after('max_concurrent_connections')
                    ->comment('Current number of active connections');
            }

            // Device fingerprint for security
            if (!$schema->hasColumn('subscriptions', 'device_fingerprint')) {
                $table->string('device_fingerprint', 64)->nullable()->after('device_mac')
                    ->comment('Unique device fingerprint hash');
            }

            // Last active IP for security tracking
            if (!$schema->hasColumn('subscriptions', 'last_ip_address')) {
                $table->string('last_ip_address', 45)->nullable()->after('ip_address')
                    ->comment('Most recent IP address used');
            }
        });

        // Migrate data from old structure
        $this->migrateSubscriberDataToSubscriptions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        $schema->table('subscriptions', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn([
                'max_concurrent_connections',
                'current_connections',
                'device_fingerprint',
                'last_ip_address'
            ]);
        });
    }

    /**
     * Migrate data from old subscribers table to subscriptions
     */
    private function migrateSubscriberDataToSubscriptions(): void
    {
        $schema = Capsule::schema();

        // Set max_concurrent_connections from the package default
        // Check which column exists in packages table (max_connections is old, max_concurrent_devices is new)
        if ($schema->hasColumn('packages', 'max_connections')) {
            // Old structure
            Capsule::statement('
                UPDATE subscriptions s
                INNER JOIN packages p ON s.package_id = p.id
                SET s.max_concurrent_connections = COALESCE(p.max_connections, 1)
                WHERE s.max_concurrent_connections IS NULL OR s.max_concurrent_connections = 0
            ');
        } elseif ($schema->hasColumn('packages', 'max_concurrent_devices')) {
            // New structure
            Capsule::statement('
                UPDATE subscriptions s
                INNER JOIN packages p ON s.package_id = p.id
                SET s.max_concurrent_connections = COALESCE(p.max_concurrent_devices, 1)
                WHERE s.max_concurrent_connections IS NULL OR s.max_concurrent_connections = 0
            ');
        }
    }
};

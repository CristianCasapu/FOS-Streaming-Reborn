<?php
/**
 * Find missing table migrations by comparing models to existing migrations
 */

require_once __DIR__ . '/config.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "=== Finding Missing Table Migrations ===\n\n";

// Tables that should exist based on models
$expectedTables = [
    'admins',                    // Admin/Staff management
    'categories',                // Stream categories
    'settings',                  // Application settings
    'transcodes',                // Transcode profiles
    'activity',                  // Activity logs
    'banned_ips',                // Security
    'blocked_ips',               // Security
    'blocked_user_agents',       // Security
    'failed_login_attempts',     // Security
    'security_events',           // Security
    'ufw_rules',                 // Firewall rules
    'device_fingerprints',       // Device locking
    'device_sessions',           // Device sessions
    'device_bindings',           // Device bindings
    'device_violations',         // Device violations
    'stream_health_logs',        // Monitoring
    'website_health_logs',       // Monitoring
    'system_command_logs',       // System commands

    // Already created (verify):
    'streams',
    'bouquets',
    'packages',
    'package_bouquet',
    'subscribers',
    'subscriptions',
    'trials',
    'pm2_workers',
    'audit_logs',
    'resellers',
    'reseller_subscribers',
    'reseller_transactions',
    'v2ray_users',
    'v2ray_servers',
    'v2ray_logs',
    'v2ray_traffic_stats',
];

$existingTables = [];
$missingTables = [];

foreach ($expectedTables as $table) {
    if (Capsule::schema()->hasTable($table)) {
        $existingTables[] = $table;
    } else {
        $missingTables[] = $table;
    }
}

echo "✓ Existing tables: " . count($existingTables) . "\n";
foreach ($existingTables as $table) {
    echo "   ✓ $table\n";
}

echo "\n✗ Missing tables: " . count($missingTables) . "\n";
foreach ($missingTables as $table) {
    echo "   ✗ $table\n";
}

if (empty($missingTables)) {
    echo "\n✓✓✓ All tables exist!\n";
} else {
    echo "\n⚠ Need to create " . count($missingTables) . " migrations\n";
}

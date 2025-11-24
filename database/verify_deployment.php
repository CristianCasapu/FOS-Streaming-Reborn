<?php
/**
 * Database Deployment Verification Script
 *
 * Checks if database is properly deployed with all required tables and data
 *
 * Usage: php database/verify_deployment.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Set up database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => env('DB_HOST', 'localhost'),
    'database' => env('DB_DATABASE', 'fos_streaming'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     DATABASE DEPLOYMENT VERIFICATION                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$errors = [];
$warnings = [];
$passed = 0;
$failed = 0;

/**
 * Helper function to check table existence
 */
function checkTable($tableName, $expectedMinRows = null) {
    global $errors, $warnings, $passed, $failed;

    try {
        $exists = Capsule::schema()->hasTable($tableName);

        if (!$exists) {
            $failed++;
            $errors[] = "Table '{$tableName}' does not exist";
            echo "✗ Table '{$tableName}': NOT FOUND\n";
            return false;
        }

        $count = Capsule::table($tableName)->count();

        if ($expectedMinRows !== null && $count < $expectedMinRows) {
            $warnings[] = "Table '{$tableName}' has only {$count} rows (expected at least {$expectedMinRows})";
            echo "⚠ Table '{$tableName}': EXISTS (but only {$count} rows, expected {$expectedMinRows}+)\n";
        } else {
            $passed++;
            echo "✓ Table '{$tableName}': EXISTS ({$count} rows)\n";
        }

        return true;
    } catch (Exception $e) {
        $failed++;
        $errors[] = "Error checking '{$tableName}': " . $e->getMessage();
        echo "✗ Table '{$tableName}': ERROR\n";
        return false;
    }
}

// === Check Core Tables ===
echo "\n--- Core Tables ---\n";
checkTable('subscribers');
checkTable('streams');
checkTable('packages', 4);
checkTable('bouquets', 8);
checkTable('subscriptions');
checkTable('trials');

// === Check Auth/Staff Tables ===
echo "\n--- Auth/Staff Tables ---\n";
checkTable('staff', 1);
checkTable('staff_activity_logs');
checkTable('staff_sessions');

// === Check Configuration Tables ===
echo "\n--- Configuration Tables ---\n";
checkTable('categories');
checkTable('transcodes');
checkTable('settings', 1);
checkTable('pm2_workers', 8);

// === Check Monitoring Tables ===
echo "\n--- Monitoring Tables ---\n";
checkTable('activity');
checkTable('stream_health_logs');
checkTable('website_health_logs');
checkTable('system_command_logs');

// === Check Security Tables ===
echo "\n--- Security Tables ---\n";
checkTable('banned_ips');
checkTable('blocked_ips');
checkTable('blocked_user_agents');
checkTable('failed_login_attempts');
checkTable('security_events');
checkTable('ufw_rules');

// === Check Device Management Tables ===
echo "\n--- Device Management Tables ---\n";
checkTable('device_fingerprints');
checkTable('device_sessions');
checkTable('device_bindings');
checkTable('device_violations');

// === Check SaaS/Reseller Tables ===
echo "\n--- SaaS/Reseller Tables ---\n";
checkTable('resellers');
checkTable('reseller_subscribers');
checkTable('reseller_transactions');

// === Check V2Ray/Proxy Tables ===
echo "\n--- V2Ray/Proxy Tables ---\n";
checkTable('v2ray_users');
checkTable('v2ray_servers');
checkTable('v2ray_logs');
checkTable('v2ray_traffic_stats');

// === Check Other Tables ===
echo "\n--- Other Tables ---\n";
checkTable('audit_logs');
checkTable('package_bouquet');
checkTable('migrations', 24);

// === Check Database Charset ===
echo "\n--- Database Charset ---\n";
try {
    $dbName = env('DB_DATABASE', 'fos_streaming');
    $result = Capsule::select("
        SELECT
            DEFAULT_CHARACTER_SET_NAME as charset,
            DEFAULT_COLLATION_NAME as collation
        FROM information_schema.SCHEMATA
        WHERE SCHEMA_NAME = ?
    ", [$dbName]);

    if (!empty($result)) {
        $charset = $result[0]->charset;
        $collation = $result[0]->collation;

        if ($charset === 'utf8mb4' && $collation === 'utf8mb4_unicode_ci') {
            $passed++;
            echo "✓ Database charset: {$charset} (correct)\n";
            echo "✓ Database collation: {$collation} (correct)\n";
            $passed++;
        } else {
            $failed++;
            $errors[] = "Database charset/collation incorrect: {$charset}/{$collation}";
            echo "✗ Database charset: {$charset} (expected utf8mb4)\n";
            echo "✗ Database collation: {$collation} (expected utf8mb4_unicode_ci)\n";
            $failed++;
        }
    }
} catch (Exception $e) {
    $failed++;
    $errors[] = "Error checking charset: " . $e->getMessage();
    echo "✗ Charset check: ERROR\n";
}

// === Check Admin Account ===
echo "\n--- Admin Account ---\n";
try {
    $admin = Capsule::table('staff')->where('username', 'admin')->first();

    if ($admin) {
        $passed++;
        echo "✓ Admin account exists (username: {$admin->username}, role: {$admin->role})\n";
    } else {
        $warnings[] = "No admin account found - run: php database/seeders/AdminSeeder.php";
        echo "⚠ Admin account: NOT FOUND (run AdminSeeder.php)\n";
    }
} catch (Exception $e) {
    $warnings[] = "Could not check admin account: " . $e->getMessage();
    echo "⚠ Admin account check: ERROR\n";
}

// === Summary ===
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICATION SUMMARY                                      ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
printf("║  Passed:   %2d                                              ║\n", $passed);
printf("║  Failed:   %2d                                              ║\n", $failed);
printf("║  Warnings: %2d                                              ║\n", count($warnings));
echo "╚════════════════════════════════════════════════════════════╝\n";

// === Display Errors ===
if (!empty($errors)) {
    echo "\n❌ ERRORS:\n";
    foreach ($errors as $error) {
        echo "   • {$error}\n";
    }
}

// === Display Warnings ===
if (!empty($warnings)) {
    echo "\n⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   • {$warning}\n";
    }
}

// === Recommendations ===
if ($failed > 0) {
    echo "\n📋 RECOMMENDATIONS:\n";
    echo "   Run the following commands to fix issues:\n";
    echo "   1. php database/migrate.php      # Run missing migrations\n";
    echo "   2. php database/seed.php         # Seed missing data\n";
    echo "   3. php database/verify_deployment.php  # Re-run verification\n";
} elseif (count($warnings) > 0) {
    echo "\n📋 RECOMMENDATIONS:\n";
    echo "   Run the following commands to address warnings:\n";
    echo "   • php database/seeders/AdminSeeder.php  # Create admin account\n";
} else {
    echo "\n🎉 SUCCESS!\n";
    echo "   Database is fully deployed and ready to use.\n";
    echo "\n   Admin Login: http://localhost:7777/admin#/login\n";
}

echo "\n";

// Exit with appropriate code
exit($failed > 0 ? 1 : 0);

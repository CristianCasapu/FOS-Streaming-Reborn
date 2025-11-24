<?php
/**
 * Laravel-style Database Seeder Runner
 *
 * This script runs database seeders manually without requiring full Laravel installation
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

// Mock command object for seeders
$command = new class {
    public function info($message) {
        echo "  ✓ $message\n";
    }
};

echo "\n=== Running Database Seeders ===\n\n";

try {
    // Run PackagesSeeder
    echo "→ Seeding packages...\n";
    require_once __DIR__ . '/seeders/PackagesSeeder.php';
    $seeder = new Database\Seeders\PackagesSeeder();
    $seeder->command = $command;
    $seeder->run();

    // Run BouquetsSeeder
    echo "\n→ Seeding bouquets...\n";
    require_once __DIR__ . '/seeders/BouquetsSeeder.php';
    $seeder = new Database\Seeders\BouquetsSeeder();
    $seeder->command = $command;
    $seeder->run();

    // Run PackageBouquetSeeder
    echo "\n→ Seeding package-bouquet relationships...\n";
    require_once __DIR__ . '/seeders/PackageBouquetSeeder.php';
    $seeder = new Database\Seeders\PackageBouquetSeeder();
    $seeder->command = $command;
    $seeder->run();

    // Run PM2WorkersSeeder
    echo "\n→ Seeding PM2 workers...\n";
    require_once __DIR__ . '/seeders/PM2WorkersSeeder.php';
    $seeder = new Database\Seeders\PM2WorkersSeeder();
    $seeder->command = $command;
    $seeder->run();

    // Run SettingsSeeder
    echo "\n→ Seeding settings...\n";
    require_once __DIR__ . '/seeders/SettingsSeeder.php';
    $seeder = new Database\Seeders\SettingsSeeder();
    $seeder->command = $command;
    $seeder->run();

    // Run AdminRolesSeeder
    echo "\n→ Seeding admin roles...\n";
    require_once __DIR__ . '/seeders/AdminRolesSeeder.php';
    $seeder = new AdminRolesSeeder();
    $seeder->run();

    // Run ResellersSeeder
    echo "\n→ Seeding resellers...\n";
    require_once __DIR__ . '/seeders/ResellersSeeder.php';
    $seeder = new ResellersSeeder();
    $seeder->run();

    // Run V2RayServersSeeder
    echo "\n→ Seeding V2Ray servers...\n";
    require_once __DIR__ . '/seeders/V2RayServersSeeder.php';
    $seeder = new V2RayServersSeeder();
    $seeder->run();

    echo "\n✓ All seeders completed successfully!\n\n";
} catch (Exception $e) {
    echo "\n✗ Seeding failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

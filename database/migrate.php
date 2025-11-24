<?php
/**
 * Laravel-style Migration Runner
 *
 * This script runs Laravel migrations manually without requiring full Laravel installation
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

// Create migrations table if it doesn't exist
try {
    Capsule::schema()->create('migrations', function ($table) {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });
    echo "✓ Created migrations table\n";
} catch (Exception $e) {
    // Table already exists, that's fine
}

// Get list of migration files
$migrationsPath = __DIR__ . '/migrations/laravel';
$migrationFiles = glob($migrationsPath . '/*.php');
sort($migrationFiles);

// Get already run migrations
$ranMigrations = Capsule::table('migrations')->pluck('migration')->toArray();

// Determine next batch number
$nextBatch = Capsule::table('migrations')->max('batch') + 1;

echo "\n=== Running Migrations ===\n\n";

$migrationsRun = 0;

foreach ($migrationFiles as $file) {
    $migrationName = basename($file, '.php');

    // Skip if already run
    if (in_array($migrationName, $ranMigrations)) {
        echo "⊘ Skipping: $migrationName (already run)\n";
        continue;
    }

    echo "→ Running: $migrationName\n";

    try {
        // Include and run the migration
        $migration = require $file;
        $migration->up();

        // Record migration
        Capsule::table('migrations')->insert([
            'migration' => $migrationName,
            'batch' => $nextBatch
        ]);

        echo "✓ Completed: $migrationName\n\n";
        $migrationsRun++;
    } catch (Exception $e) {
        echo "✗ Failed: $migrationName\n";
        echo "  Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

if ($migrationsRun === 0) {
    echo "✓ Nothing to migrate - all migrations have been run\n";
} else {
    echo "✓ Successfully ran $migrationsRun migration(s)\n";
}

echo "\n";

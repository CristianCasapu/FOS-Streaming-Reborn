<?php
/**
 * Database Recreation Script
 *
 * Drops and recreates the database with proper UTF8MB4 charset
 * WARNING: This will DELETE ALL DATA in the database!
 *
 * Usage: php database/recreate_database.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Get database credentials from .env
$dbHost = env('DB_HOST', 'localhost');
$dbName = env('DB_DATABASE', 'fos_streaming');
$dbUser = env('DB_USERNAME', 'root');
$dbPass = env('DB_PASSWORD', '');

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          DATABASE RECREATION SCRIPT                        ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  ⚠️  WARNING: This will DELETE ALL DATA!                   ║\n";
echo "║                                                            ║\n";
echo "║  Database: {$dbName}" . str_repeat(' ', 49 - strlen($dbName)) . "║\n";
echo "║  Host: {$dbHost}" . str_repeat(' ', 53 - strlen($dbHost)) . "║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Confirmation prompt
$response = readline("Are you sure you want to DROP and RECREATE the database? (type 'yes' to confirm): ");

if (strtolower(trim($response)) !== 'yes') {
    echo "\n✗ Operation cancelled by user.\n\n";
    exit(0);
}

try {
    // Connect to MySQL without selecting database
    $pdo = new PDO(
        "mysql:host={$dbHost};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "\n→ Connected to MySQL server\n";

    // Drop database if exists
    echo "→ Dropping database '{$dbName}' if exists...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    echo "✓ Database dropped\n";

    // Create database with UTF8MB4
    echo "→ Creating database '{$dbName}' with UTF8MB4...\n";
    $pdo->exec("CREATE DATABASE `{$dbName}`
                CHARACTER SET utf8mb4
                COLLATE utf8mb4_unicode_ci");
    echo "✓ Database created\n";

    // Verify charset
    $stmt = $pdo->query("
        SELECT
            DEFAULT_CHARACTER_SET_NAME as charset,
            DEFAULT_COLLATION_NAME as collation
        FROM information_schema.SCHEMATA
        WHERE SCHEMA_NAME = '{$dbName}'
    ");
    $dbInfo = $stmt->fetch();

    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║  Database Created Successfully                             ║\n";
    echo "╠════════════════════════════════════════════════════════════╣\n";
    echo "║  Name: {$dbName}" . str_repeat(' ', 53 - strlen($dbName)) . "║\n";
    echo "║  Charset: {$dbInfo['charset']}" . str_repeat(' ', 49 - strlen($dbInfo['charset'])) . "║\n";
    echo "║  Collation: {$dbInfo['collation']}" . str_repeat(' ', 47 - strlen($dbInfo['collation'])) . "║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";

    echo "\n📋 Next Steps:\n";
    echo "   1. Run migrations:  php database/migrate.php\n";
    echo "   2. Run seeders:     php database/seed.php\n";
    echo "   3. Create admin:    php database/seeders/AdminSeeder.php\n\n";

} catch (PDOException $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

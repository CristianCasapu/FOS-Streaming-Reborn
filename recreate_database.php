<?php
/**
 * Recreate database for fresh testing
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dbName = env('DB_DATABASE', 'fos_streaming');
$dbUser = env('DB_USERNAME', 'root');
$dbPass = env('DB_PASSWORD', '');
$dbHost = env('DB_HOST', 'localhost');

echo "=== Recreating Database ===\n\n";
echo "Database: $dbName\n";
echo "Host: $dbHost\n";
echo "User: $dbUser\n\n";

try {
    // Connect without database selection
    $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "→ Dropping database if exists...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `$dbName`");
    echo "✓ Database dropped\n\n";

    echo "→ Creating fresh database...\n";
    $pdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database created\n\n";

    echo "=== Database Ready ===\n";
    echo "You can now run: php database/migrate.php\n";

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

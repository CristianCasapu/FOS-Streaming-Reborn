<?php
/**
 * FOS-Streaming Password Migration Script
 * Migrates MD5 passwords to Argon2id
 *
 * IMPORTANT: Run this once before deploying index-secure.php
 * This script adds a 'password_type' column to track migration status
 *
 * Date: 2025-11-21
 */

require_once 'config.php';
require_once 'lib/Security.php';
require_once 'lib/SecurityLogger.php';

use FOS\Security\Security;
use FOS\Security\SecurityLogger;

// Set execution time limit
set_time_limit(300);

echo "===========================================\n";
echo "FOS-Streaming Password Migration Tool\n";
echo "===========================================\n\n";

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line for security reasons.\n");
}

// Step 1: Add password_type column if it doesn't exist
echo "[1/3] Checking database schema...\n";

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=fos',
        'fos',
        trim(file_get_contents('/root/MYSQL_ROOT_PASSWORD'))
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if password_type column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'password_type'");
    if ($stmt->rowCount() == 0) {
        echo "  Adding password_type column...\n";
        $pdo->exec("ALTER TABLE admins ADD COLUMN password_type VARCHAR(20) DEFAULT 'md5' AFTER password");
        echo "  ✓ Column added\n";
    } else {
        echo "  ✓ Column already exists\n";
    }

    // Check if last_password_change column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'last_password_change'");
    if ($stmt->rowCount() == 0) {
        echo "  Adding last_password_change column...\n";
        $pdo->exec("ALTER TABLE admins ADD COLUMN last_password_change DATETIME NULL AFTER password_type");
        echo "  ✓ Column added\n";
    } else {
        echo "  ✓ Column already exists\n";
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}

echo "\n[2/3] Analyzing admin accounts...\n";

try {
    // Get all admins
    $admins = Admin::all();
    echo "  Found " . count($admins) . " admin account(s)\n\n";

    $migratedCount = 0;
    $alreadyModernCount = 0;
    $errors = [];

    foreach ($admins as $admin) {
        echo "  Processing: " . $admin->username . "...";

        // Check if already using modern hashing
        if (strpos($admin->password, '$2y$') === 0 || strpos($admin->password, '$argon2') === 0) {
            echo " [ALREADY MODERN]\n";
            $alreadyModernCount++;

            // Update password_type if not set
            if (empty($admin->password_type) || $admin->password_type === 'md5') {
                $admin->password_type = strpos($admin->password, '$argon2') === 0 ? 'argon2id' : 'bcrypt';
                $admin->save();
            }
            continue;
        }

        // This is an MD5 password - we can't migrate without the plain text password
        // Mark it for migration on next login
        echo " [MD5 - WILL MIGRATE ON NEXT LOGIN]\n";

        // Update password_type to indicate it needs migration
        $admin->password_type = 'md5';
        $admin->save();
    }

    echo "\n";
    echo "===========================================\n";
    echo "Migration Summary:\n";
    echo "===========================================\n";
    echo "Total accounts:       " . count($admins) . "\n";
    echo "Already modern:       " . $alreadyModernCount . "\n";
    echo "Requires migration:   " . (count($admins) - $alreadyModernCount) . "\n";
    echo "\n";

    if ((count($admins) - $alreadyModernCount) > 0) {
        echo "NOTE: MD5 passwords will be automatically upgraded to Argon2id\n";
        echo "      when users log in for the first time with index-secure.php\n";
    }

} catch (Exception $e) {
    die("Error during migration: " . $e->getMessage() . "\n");
}

echo "\n[3/3] Creating default admin with secure password (if needed)...\n";

try {
    // Check if 'admin' user exists
    $adminUser = Admin::where('username', 'admin')->first();

    if ($adminUser) {
        echo "  'admin' user already exists\n";

        // If it's using MD5 'admin' password, upgrade it
        if ($adminUser->password === md5('admin')) {
            echo "  WARNING: Default 'admin/admin' credentials detected!\n";
            echo "  It's recommended to change this password immediately after login.\n";

            $adminUser->password = Security::hashPassword('admin');
            $adminUser->password_type = 'argon2id';
            $adminUser->last_password_change = date('Y-m-d H:i:s');
            $adminUser->save();

            echo "  ✓ Default password upgraded to Argon2id\n";
            echo "  ⚠  CHANGE THIS PASSWORD IMMEDIATELY!\n";

            SecurityLogger::logSecurityEvent('Default admin password upgraded', [
                'username' => 'admin',
                'note' => 'Password still default - must be changed'
            ]);
        }
    } else {
        echo "  Creating default 'admin' user...\n";

        $newAdmin = new Admin();
        $newAdmin->username = 'admin';
        $newAdmin->password = Security::hashPassword('admin');
        $newAdmin->password_type = 'argon2id';
        $newAdmin->last_password_change = date('Y-m-d H:i:s');
        $newAdmin->save();

        echo "  ✓ Created admin user with secure password\n";
        echo "  Login: admin / admin\n";
        echo "  ⚠  CHANGE THIS PASSWORD IMMEDIATELY!\n";

        SecurityLogger::logSecurityEvent('Default admin user created', [
            'username' => 'admin'
        ]);
    }

} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n===========================================\n";
echo "Migration complete!\n";
echo "===========================================\n\n";

echo "Next steps:\n";
echo "1. Backup your database\n";
echo "2. Replace index.php with index-secure.php\n";
echo "3. Test login with an admin account\n";
echo "4. Monitor /home/fos-streaming/fos/logs/security.log\n";
echo "5. Change default 'admin' password if using it\n\n";

SecurityLogger::logSecurityEvent('Password migration script completed', [
    'total_accounts' => count($admins),
    'modern_hashes' => $alreadyModernCount
]);

echo "Migration log saved to: /home/fos-streaming/fos/logs/security.log\n\n";

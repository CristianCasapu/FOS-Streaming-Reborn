<?php

/**
 * Staff Seeder
 *
 * Creates default staff account for initial system access
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
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

// Load models
require_once __DIR__ . '/../../models/Staff.php';

echo "=== Seeding Staff ===\n\n";

// Check if staff already exists
$existingStaff = Staff::where('username', 'admin')->first();

if ($existingStaff) {
    echo "⚠ Default admin account already exists\n";
    echo "  Username: admin\n";
    echo "  Status: {$existingStaff->status}\n";
    echo "  Role: {$existingStaff->role}\n\n";

    // Update password if needed
    $response = readline("Do you want to reset the admin password to 'admin'? (yes/no): ");
    if (strtolower(trim($response)) === 'yes') {
        $existingStaff->password = md5('admin');
        $existingStaff->save();
        echo "✓ Admin password reset successfully\n\n";
    }
} else {
    // Create default staff account
    $staff = Staff::create([
        'username' => 'admin',
        'email' => 'admin@fosstreaming.local',
        'password' => md5('admin'),  // Default password: admin
        'role' => 'admin',
        'full_name' => 'System Administrator',
        'status' => 'active',
        'permissions' => json_encode([
            'manage_admins',
            'manage_subscribers',
            'manage_streams',
            'manage_packages',
            'manage_settings',
            'view_logs',
            'manage_security'
        ]),
        'login_count' => 0,
        'force_password_change' => true,  // Force password change on first login
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    echo "✓ Default staff account created successfully\n\n";
    echo "╔═══════════════════════════════════════╗\n";
    echo "║     DEFAULT STAFF CREDENTIALS         ║\n";
    echo "╠═══════════════════════════════════════╣\n";
    echo "║  Username: admin                      ║\n";
    echo "║  Password: admin                      ║\n";
    echo "║  Role: Administrator                  ║\n";
    echo "╠═══════════════════════════════════════╣\n";
    echo "║  ⚠ SECURITY WARNING:                  ║\n";
    echo "║  Change the default password          ║\n";
    echo "║  immediately after first login!       ║\n";
    echo "╚═══════════════════════════════════════╝\n\n";
}

// Display login URL
echo "🌐 Admin Login URL:\n";
echo "   http://localhost:7777/admin#/login\n";
echo "   or\n";
echo "   http://127.0.0.1:7777/admin#/login\n\n";

echo "=== Staff Seeding Complete ===\n";

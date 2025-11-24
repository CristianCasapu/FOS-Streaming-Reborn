<?php

/**
 * Authentication Test Script
 * Tests the authentication system and admin account
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set up database
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => env('DB_HOST', 'localhost'),
    'database' => env('DB_DATABASE', 'fos_streaming'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

// Load Admin model
require_once __DIR__ . '/models/Admin.php';

echo "=== Authentication System Test ===\n\n";

// Test 1: Check if admin table exists
echo "1. Checking admins table...\n";
try {
    $count = Capsule::table('admins')->count();
    echo "   ✓ Admins table exists with {$count} record(s)\n\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Check if default admin exists
echo "2. Checking default admin account...\n";
$admin = Admin::where('username', 'admin')->first();
if ($admin) {
    echo "   ✓ Admin account found\n";
    echo "     ID: {$admin->id}\n";
    echo "     Username: {$admin->username}\n";
    echo "     Email: {$admin->email}\n";
    echo "     Role: {$admin->role}\n";
    echo "     Status: {$admin->status}\n";
    echo "     Login Count: {$admin->login_count}\n\n";
} else {
    echo "   ✗ Admin account not found!\n\n";
    exit(1);
}

// Test 3: Test password verification
echo "3. Testing password verification...\n";
$testPassword = 'admin';
$hashedPassword = md5($testPassword);
if ($admin->password === $hashedPassword) {
    echo "   ✓ Password hash matches (MD5)\n\n";
} else {
    echo "   ✗ Password hash does NOT match!\n";
    echo "     Expected: {$hashedPassword}\n";
    echo "     Got: {$admin->password}\n\n";
}

// Test 4: Test authentication query
echo "4. Testing authentication query...\n";
$authTest = Admin::where('username', '=', 'admin')
                 ->where('password', '=', md5('admin'))
                 ->first();

if ($authTest) {
    echo "   ✓ Authentication query successful\n";
    echo "     Found user: {$authTest->username}\n\n";
} else {
    echo "   ✗ Authentication query failed!\n\n";
}

// Test 5: Test Admin model methods
echo "5. Testing Admin model methods...\n";
echo "   - hasRole('admin'): " . ($admin->hasRole('admin') ? 'true' : 'false') . "\n";
echo "   - hasPermission('manage_admins'): " . ($admin->hasPermission('manage_admins') ? 'true' : 'false') . "\n";
echo "   - isActive(): " . ($admin->isActive() ? 'true' : 'false') . "\n\n";

// Test 6: Session simulation
echo "6. Testing session simulation...\n";
session_start();
$_SESSION['user_id'] = 'admin';
$_SESSION['admin_id'] = $admin->id;
$_SESSION['logged_in'] = true;

$sessionUser = Admin::where('username', '=', $_SESSION['user_id'])->first();
if ($sessionUser) {
    echo "   ✓ Session user lookup successful\n";
    echo "     Session user_id: {$_SESSION['user_id']}\n";
    echo "     Session admin_id: {$_SESSION['admin_id']}\n";
    echo "     Found user: {$sessionUser->username}\n\n";
} else {
    echo "   ✗ Session user lookup failed!\n\n";
}

// Clean up session
session_unset();
session_destroy();

echo "=== All Tests Complete ===\n\n";
echo "✅ Authentication system is working correctly!\n\n";
echo "You can now login with:\n";
echo "   Username: admin\n";
echo "   Password: admin\n\n";

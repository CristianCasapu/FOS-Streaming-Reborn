<?php
/**
 * Test script for device locking implementation
 * Run: php test_device_locking.php
 */

require_once 'config.php';

// Load new models
require_once 'models/DeviceFingerprint.php';
require_once 'models/DeviceBinding.php';
require_once 'models/DeviceViolation.php';
require_once 'models/DeviceSession.php';

// Colors for console output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

echo "{$blue}========================================{$reset}\n";
echo "{$blue}Device Locking System Test{$reset}\n";
echo "{$blue}========================================{$reset}\n\n";

// Test 1: Database Tables
echo "{$yellow}Test 1: Checking Database Tables{$reset}\n";
$tables = [
    'device_fingerprints',
    'device_bindings',
    'device_violations',
    'device_sessions'
];

foreach ($tables as $table) {
    $result = \Illuminate\Database\Capsule\Manager::select("SHOW TABLES LIKE '$table'");
    if (!empty($result)) {
        echo "  {$green}✓{$reset} Table '$table' exists\n";
    } else {
        echo "  {$red}✗{$reset} Table '$table' missing\n";
    }
}

// Test 2: Model Instantiation
echo "\n{$yellow}Test 2: Testing Model Classes{$reset}\n";
$models = [
    'DeviceFingerprint',
    'DeviceBinding',
    'DeviceViolation',
    'DeviceSession'
];

foreach ($models as $model) {
    if (class_exists($model)) {
        $instance = new $model();
        echo "  {$green}✓{$reset} Model '$model' loaded successfully\n";
    } else {
        echo "  {$red}✗{$reset} Model '$model' not found\n";
    }
}

// Test 3: Create Test Data
echo "\n{$yellow}Test 3: Creating Test Device Fingerprint{$reset}\n";

// Get or create a test subscriber
$subscriber = Subscriber::first();
if (!$subscriber) {
    $subscriber = Subscriber::create([
        'username' => 'test_subscriber',
        'password' => 'test123',
        'email' => 'test@example.com',
        'enabled' => 1,
        'max_connections' => 1
    ]);
    echo "  {$green}✓{$reset} Created test subscriber\n";
} else {
    echo "  {$blue}ℹ{$reset} Using existing subscriber: {$subscriber->username}\n";
}

// Create test fingerprint
$testFingerprintData = [
    'canvas_fingerprint' => hash('sha256', 'test_canvas'),
    'webgl_fingerprint' => hash('sha256', 'test_webgl'),
    'audio_fingerprint' => hash('sha256', 'test_audio'),
    'timezone' => 'America/Chicago',
    'screen_resolution' => '1920x1080',
    'platform' => 'Test Platform',
    'user_agent' => 'Test User Agent'
];

$deviceId = DeviceFingerprint::generateDeviceId($testFingerprintData);
echo "  {$blue}ℹ{$reset} Generated device ID: " . substr($deviceId, 0, 16) . "...\n";

// Check if fingerprint exists
$fingerprint = DeviceFingerprint::where('device_id', $deviceId)
    ->where('subscriber_id', $subscriber->id)
    ->first();

if (!$fingerprint) {
    $fingerprint = DeviceFingerprint::create([
        'subscriber_id' => $subscriber->id,
        'device_id' => $deviceId,
        'browser_fingerprint' => json_encode($testFingerprintData),
        'canvas_fingerprint' => $testFingerprintData['canvas_fingerprint'],
        'webgl_fingerprint' => $testFingerprintData['webgl_fingerprint'],
        'audio_fingerprint' => $testFingerprintData['audio_fingerprint'],
        'timezone' => $testFingerprintData['timezone'],
        'screen_resolution' => $testFingerprintData['screen_resolution'],
        'platform' => $testFingerprintData['platform'],
        'user_agent' => $testFingerprintData['user_agent']
    ]);
    echo "  {$green}✓{$reset} Created device fingerprint\n";
} else {
    echo "  {$blue}ℹ{$reset} Device fingerprint already exists\n";
}

// Test 4: Device Binding
echo "\n{$yellow}Test 4: Testing Device Binding{$reset}\n";

// Get or create a subscription
$subscription = $subscriber->subscriptions()->first();
if (!$subscription) {
    // Create a test package first
    $package = Package::first();
    if (!$package) {
        $package = Package::create([
            'name' => 'Test Package',
            'description' => 'Test package for device locking',
            'max_connections' => 1,
            'max_devices' => 3,
            'max_concurrent_streams' => 1,
            'device_lock_enabled' => 1
        ]);
        echo "  {$green}✓{$reset} Created test package\n";
    }

    $subscription = Subscription::create([
        'subscriber_id' => $subscriber->id,
        'package_id' => $package->id,
        'expire_date' => date('Y-m-d', strtotime('+30 days')),
        'is_active' => 1,
        'max_devices' => 3,
        'max_concurrent_streams' => 1,
        'device_lock_enabled' => 1
    ]);
    echo "  {$green}✓{$reset} Created test subscription\n";
} else {
    echo "  {$blue}ℹ{$reset} Using existing subscription\n";
}

// Create device binding
$binding = DeviceBinding::where('subscription_id', $subscription->id)
    ->where('device_fingerprint_id', $fingerprint->id)
    ->first();

if (!$binding) {
    $binding = DeviceBinding::create([
        'subscription_id' => $subscription->id,
        'device_fingerprint_id' => $fingerprint->id,
        'device_name' => 'Test Device',
        'ip_address' => '127.0.0.1',
        'is_primary' => true,
        'is_active' => true
    ]);
    echo "  {$green}✓{$reset} Created device binding\n";
} else {
    echo "  {$blue}ℹ{$reset} Device binding already exists\n";
}

// Test 5: Session Creation
echo "\n{$yellow}Test 5: Testing Session Management{$reset}\n";

$session = DeviceSession::create([
    'device_binding_id' => $binding->id,
    'stream_id' => null,
    'session_token' => bin2hex(random_bytes(32)),
    'ip_address' => '127.0.0.1',
    'user_agent' => 'Test User Agent',
    'is_active' => true
]);

if ($session->id) {
    echo "  {$green}✓{$reset} Created device session\n";
    echo "  {$blue}ℹ{$reset} Session token: " . substr($session->session_token, 0, 16) . "...\n";
} else {
    echo "  {$red}✗{$reset} Failed to create session\n";
}

// Test 6: Violation Logging
echo "\n{$yellow}Test 6: Testing Violation Logging{$reset}\n";

$violation = DeviceViolation::create([
    'subscriber_id' => $subscriber->id,
    'subscription_id' => $subscription->id,
    'device_fingerprint_id' => $fingerprint->id,
    'violation_type' => 'concurrent_limit',
    'severity' => 'medium',
    'details' => json_encode(['test' => true]),
    'action_taken' => 'logged',
    'ip_address' => '127.0.0.1',
    'device_info' => 'Test Device'
]);

if ($violation->id) {
    echo "  {$green}✓{$reset} Created violation log\n";
    echo "  {$blue}ℹ{$reset} Violation type: {$violation->violation_type}\n";
} else {
    echo "  {$red}✗{$reset} Failed to create violation\n";
}

// Test 7: Service Functionality
echo "\n{$yellow}Test 7: Testing DeviceFingerprintService{$reset}\n";

if (file_exists('app/Services/DeviceFingerprintService.php')) {
    require_once 'app/Services/DeviceFingerprintService.php';
    $service = new App\Services\DeviceFingerprintService();
    echo "  {$green}✓{$reset} DeviceFingerprintService loaded\n";

    // Test similarity calculation
    $similarity = $service->calculateSimilarity($fingerprint, $testFingerprintData);
    echo "  {$blue}ℹ{$reset} Fingerprint similarity: " . ($similarity * 100) . "%\n";
} else {
    echo "  {$red}✗{$reset} DeviceFingerprintService not found\n";
}

// Test 8: Check SRT Installation
echo "\n{$yellow}Test 8: Checking SRT Installation{$reset}\n";

// Check SRT library
$srtLib = shell_exec('pkg-config --exists libsrt && echo "installed"');
if (trim($srtLib) === 'installed') {
    echo "  {$green}✓{$reset} SRT library installed\n";
} else {
    echo "  {$yellow}⚠{$reset} SRT library not detected\n";
}

// Check SRT tools
$srtTools = shell_exec('which srt-live-transmit');
if (!empty(trim($srtTools))) {
    echo "  {$green}✓{$reset} SRT tools installed\n";
} else {
    echo "  {$yellow}⚠{$reset} SRT tools not found\n";
}

// Check Node.js SRT package
if (file_exists('node_modules/node-srt/package.json')) {
    echo "  {$green}✓{$reset} Node.js SRT package installed\n";
} else {
    echo "  {$yellow}⚠{$reset} Node.js SRT package not found\n";
}

// Summary
echo "\n{$blue}========================================{$reset}\n";
echo "{$blue}Test Summary{$reset}\n";
echo "{$blue}========================================{$reset}\n";

$testResults = [
    'Database tables' => !empty($result),
    'Models loaded' => class_exists('DeviceFingerprint'),
    'Fingerprint created' => isset($fingerprint) && $fingerprint->id,
    'Binding created' => isset($binding) && $binding->id,
    'Session created' => isset($session) && $session->id,
    'Violation logged' => isset($violation) && $violation->id,
    'SRT installed' => trim($srtLib) === 'installed'
];

$passed = 0;
$failed = 0;

foreach ($testResults as $test => $result) {
    if ($result) {
        echo "  {$green}✓{$reset} $test\n";
        $passed++;
    } else {
        echo "  {$red}✗{$reset} $test\n";
        $failed++;
    }
}

echo "\n{$blue}Results: {$green}$passed passed{$reset}, {$red}$failed failed{$reset}\n";

// Clean up test data (optional)
echo "\n{$yellow}Cleanup:{$reset} Test data retained for inspection\n";
echo "{$blue}To remove test data, delete records with subscriber_id = {$subscriber->id}{$reset}\n";

echo "\n{$green}Device locking system implementation test completed!{$reset}\n";
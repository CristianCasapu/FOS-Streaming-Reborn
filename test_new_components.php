<?php
/**
 * Test New Components - Quick validation script
 */

require_once 'config.php';

echo "=== Testing New Components ===\n\n";

$results = ['passed' => 0, 'failed' => 0];

// Test 1: Check if new tables exist
echo "1. Checking Database Tables...\n";
try {
    $pdo = new PDO(
        'mysql:host='.env('DB_HOST').';dbname='.env('DB_DATABASE'),
        env('DB_USERNAME'),
        env('DB_PASSWORD')
    );

    $tables = ['audit_logs', 'resellers', 'reseller_subscribers', 'reseller_transactions',
               'v2ray_users', 'v2ray_servers', 'v2ray_logs', 'v2ray_traffic_stats',
               'admin_activity_logs', 'admin_sessions'];

    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount();
        if ($result > 0) {
            echo "   ✓ $table exists\n";
            $results['passed']++;
        } else {
            echo "   ✗ $table missing\n";
            $results['failed']++;
        }
    }
} catch (Exception $e) {
    echo "   ✗ Database check failed: " . $e->getMessage() . "\n";
    $results['failed']++;
}

echo "\n2. Checking Eloquent Models...\n";
$models = ['AuditLog', 'Reseller', 'ResellerTransaction', 'V2RayUser', 'V2RayServer'];
foreach ($models as $model) {
    if (class_exists($model)) {
        echo "   ✓ $model loaded\n";
        $results['passed']++;
    } else {
        echo "   ✗ $model not found\n";
        $results['failed']++;
    }
}

echo "\n3. Checking Services...\n";
$services = [
    'App\Services\SRTService',
    'App\Services\CDNService',
    'App\Services\HealthCheckService'
];
foreach ($services as $service) {
    if (class_exists($service)) {
        echo "   ✓ $service loaded\n";
        $results['passed']++;
    } else {
        echo "   ✗ $service not found\n";
        $results['failed']++;
    }
}

echo "\n4. Testing SRTService...\n";
try {
    $srtService = new \App\Services\SRTService();
    $passphrase = $srtService->generatePassphrase();
    echo "   ✓ SRTService operational (generated passphrase)\n";
    $results['passed']++;
} catch (Exception $e) {
    echo "   ✗ SRTService failed: " . $e->getMessage() . "\n";
    $results['failed']++;
}

echo "\n5. Testing HealthCheckService...\n";
try {
    $healthService = new \App\Services\HealthCheckService();
    $health = $healthService->checkDatabase();
    echo "   ✓ HealthCheckService operational (checked database)\n";
    echo "      - Database status: " . $health['status'] . "\n";
    echo "      - Latency: " . $health['latency_ms'] . "ms\n";
    $results['passed']++;
} catch (Exception $e) {
    echo "   ✗ HealthCheckService failed: " . $e->getMessage() . "\n";
    $results['failed']++;
}

echo "\n6. Checking Streams Table Updates...\n";
try {
    $columns = $pdo->query("SHOW COLUMNS FROM streams WHERE Field IN ('stream_type', 'stream_mode', 'protocol', 'srt_enabled', 'proxy_settings', 'encryption_settings')")->fetchAll(PDO::FETCH_COLUMN);
    $expected = ['stream_type', 'stream_mode', 'protocol', 'srt_enabled', 'proxy_settings', 'encryption_settings'];
    foreach ($expected as $col) {
        if (in_array($col, $columns)) {
            echo "   ✓ streams.$col exists\n";
            $results['passed']++;
        } else {
            echo "   ✗ streams.$col missing\n";
            $results['failed']++;
        }
    }
} catch (Exception $e) {
    echo "   ✗ Streams table check failed: " . $e->getMessage() . "\n";
    $results['failed']++;
}

echo "\n7. Checking Admins Table RBAC Updates...\n";
try {
    $columns = $pdo->query("SHOW COLUMNS FROM admins WHERE Field IN ('role', 'permissions', 'status')")->fetchAll(PDO::FETCH_COLUMN);
    $expected = ['role', 'permissions', 'status'];
    foreach ($expected as $col) {
        if (in_array($col, $columns)) {
            echo "   ✓ admins.$col exists\n";
            $results['passed']++;
        } else {
            echo "   ✗ admins.$col missing\n";
            $results['failed']++;
        }
    }
} catch (Exception $e) {
    echo "   ✗ Admins table check failed: " . $e->getMessage() . "\n";
    $results['failed']++;
}

// Summary
echo "\n=== Test Results ===\n";
echo "Passed: {$results['passed']}\n";
echo "Failed: {$results['failed']}\n";
$total = $results['passed'] + $results['failed'];
$percentage = $total > 0 ? round(($results['passed'] / $total) * 100) : 0;
echo "Success Rate: {$percentage}%\n\n";

if ($results['failed'] === 0) {
    echo "✓ All tests passed! Components are ready to use.\n";
    exit(0);
} else {
    echo "⚠ Some tests failed. Review output above.\n";
    exit(1);
}
